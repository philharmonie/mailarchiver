<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Email;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webklex\PHPIMAP\Message;

class EmailParserService
{
    /**
     * How much of an exception message may reach a log.
     */
    public const ERROR_EXCERPT = 300;

    /**
     * How much of a header each column can hold.
     *
     * A mail is under no obligation to be brief, and MySQL in strict mode
     * answers one character too many with error 1406 and drops the whole
     * insert - so the mail is not archived at all, and the next sync tries it
     * again, forever. The same shape of failure as unstorable bytes below;
     * "HM Priv" lost 45 mails per run to a subject longer than 255.
     *
     * The subject is the one worth room, because the archive is searched and
     * sorted by it: 512 characters, which is also as wide as its index may
     * get (utf8mb4 at four bytes a character, against InnoDB's 3072-byte key
     * limit). The rest keep their column width - an address or a message id
     * that long is malformed rather than long.
     *
     * Cutting here costs the archive nothing it has to keep: raw_email holds
     * the mail as it arrived, headers included, and that is the copy GoBD
     * asks to stay faithful.
     */
    public const COLUMN_LIMITS = [
        'message_id' => 255,
        'in_reply_to' => 255,
        'from_address' => 255,
        'from_name' => 255,
        'subject' => 512,
    ];

    public function __construct(
        protected CompressionService $compression,
        protected TextExtractorService $textExtractor,
        protected RawEmailStore $rawParts
    ) {}

    /**
     * How a mail is written down: the attachments are lifted out into raw
     * parts so their bytes are stored once, and what stays is compressed.
     *
     * The hash is taken over the mail as it arrived, before any of this - it
     * has to answer for the original, and RawEmailStore hands the original
     * back byte for byte.
     *
     * @return array{split: array, columns: array{raw_email: string, raw_parts: array, is_compressed: bool}}
     */
    protected function storableRaw(string $rawEmail): array
    {
        $split = $this->rawParts->split($rawEmail);
        $skeleton = $split['skeleton'];

        $compress = $this->compression->shouldCompress(strlen($skeleton));

        return [
            'split' => $split,
            'columns' => [
                'raw_email' => $compress ? $this->compression->compress($skeleton) : $skeleton,
                'raw_parts' => $split['hashes'],
                'is_compressed' => $compress,
            ],
        ];
    }

    /**
     * What may be written down when handling a mail fails.
     *
     * Everything that goes wrong in here goes wrong about a mail, and the
     * exception tends to carry it: a QueryException prints the statement it
     * failed on, and that statement is the mail - subject, addresses, body -
     * in clear text, in a file that is not the archive, once per attempt. The
     * driver says what went wrong; the statement only says what it went wrong
     * on, and the log line next to it already says that.
     */
    public static function errorExcerpt(\Throwable $e): string
    {
        $message = $e instanceof QueryException
            ? ($e->getPrevious()?->getMessage() ?? Str::before($e->getMessage(), ' (Connection: '))
            : $e->getMessage();

        return Str::limit($message, self::ERROR_EXCERPT);
    }

    /**
     * Mail as the database can hold it.
     *
     * A mail is bytes, not text: a sender may label a body utf-8 and send
     * latin-1 anyway, and a client may encode an emoji as a surrogate pair
     * that utf-8 has no room for. MySQL refuses both, and the whole insert
     * fails - so the mail is not archived at all, and the next sync tries it
     * again, forever. Replace what cannot be stored and keep the mail.
     *
     * Only the parsed columns pass through here. `raw_email` keeps the bytes
     * as they arrived, which is the copy that has to stay faithful.
     */
    public static function toUtf8(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([self::class, 'toUtf8'], $value);
        }

        if (! is_string($value) || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }

    /**
     * The same for a row about to be written, minus the columns that hold
     * bytes rather than text: what is not valid utf-8 is replaced, and what
     * is longer than its column can hold is cut.
     */
    protected static function toStorableRow(array $row): array
    {
        foreach ($row as $column => $value) {
            if ($column === 'raw_email') {
                continue;
            }

            $value = self::toUtf8($value);

            $limit = self::COLUMN_LIMITS[$column] ?? null;

            // Characters, not bytes: that is what a utf8mb4 varchar counts,
            // and cutting bytes would end a mail in half a character.
            if ($limit !== null && is_string($value) && mb_strlen($value) > $limit) {
                $value = mb_substr($value, 0, $limit);
            }

            $row[$column] = $value;
        }

        return $row;
    }

    public function parseAndStore(string $rawEmail): Email
    {
        $parsed = $this->parseRawEmail($rawEmail);

        $stored = $this->storableRaw($rawEmail);
        $this->rawParts->persist($stored['split']);

        $email = Email::create(self::toStorableRow([
            'message_id' => $parsed['message_id'],
            'in_reply_to' => $parsed['in_reply_to'],
            'references' => $parsed['references'],
            'from_address' => $parsed['from_address'],
            'from_name' => $parsed['from_name'],
            'to_addresses' => $parsed['to_addresses'],
            'cc_addresses' => $parsed['cc_addresses'],
            'bcc_addresses' => $parsed['bcc_addresses'],
            'subject' => $parsed['subject'],
            'body_text' => $parsed['body_text'],
            'headers' => $parsed['headers'],
            'received_at' => $parsed['received_at'],
            'archived_at' => now(),
            'size_bytes' => strlen($rawEmail),
            'hash' => Email::generateHash($rawEmail),
            'is_verified' => true,
            'has_attachments' => ! empty($parsed['attachments']),
            ...$stored['columns'],
        ]));

        foreach ($parsed['attachments'] ?? [] as $attachmentData) {
            $this->storeAttachment($email, $attachmentData, $stored['split']['decoded_hashes']);
        }

        return $email->fresh('attachments');
    }

    /**
     * Parse and store an email from an IMAP Message object
     */
    public function parseAndStoreFromImap(Message $message): ?Email
    {
        // Get message ID first to check for duplicates
        $messageId = $message->getMessageId() ?? '<'.Str::uuid().'@mailarchive.local>';

        // Check if email already exists
        $existingEmail = Email::where('message_id', $messageId)->first();
        if ($existingEmail) {
            // Email already archived, skip
            return null;
        }

        // Get raw email for hash and storage (header + body)
        $rawHeader = $message->getHeader()->raw ?? '';
        $rawBody = $message->getRawBody();
        $rawEmail = $rawHeader."\r\n\r\n".$rawBody;

        // Extract data using IMAP library methods (properly decoded)
        $from = $message->getFrom();
        $fromArray = $from ? $from->toArray() : [];
        $fromAddress = ! empty($fromArray) ? ($fromArray[0]->mail ?? null) : null;
        $fromName = ! empty($fromArray) ? ($fromArray[0]->personal ?? null) : null;

        $to = $message->getTo();
        $toArray = $to ? $to->toArray() : [];
        $toAddresses = ! empty($toArray) ? array_map(fn ($addr) => $addr->mail ?? null, $toArray) : null;
        $toAddresses = $toAddresses ? array_filter($toAddresses) : null;
        $toAddresses = $toAddresses && count($toAddresses) > 0 ? array_values($toAddresses) : null;

        $cc = $message->getCc();
        $ccArray = $cc ? $cc->toArray() : [];
        $ccAddresses = ! empty($ccArray) ? array_map(fn ($addr) => $addr->mail ?? null, $ccArray) : null;
        $ccAddresses = $ccAddresses ? array_filter($ccAddresses) : null;
        $ccAddresses = $ccAddresses && count($ccAddresses) > 0 ? array_values($ccAddresses) : null;

        // Get the email date from the Date header
        $dateHeader = $message->getDate();
        $receivedAt = now(); // Default to current time

        if ($dateHeader) {
            try {
                $parsedDate = $dateHeader->toDate();
                // Validate the date is reasonable (after 1990 and not in the future)
                if ($parsedDate &&
                    $parsedDate->getTimestamp() > strtotime('1990-01-01') &&
                    $parsedDate->getTimestamp() <= time() + 86400) {
                    $receivedAt = $parsedDate;
                }
            } catch (\Exception $e) {
                // Keep default (now()) if date parsing fails
            }
        }

        $stored = $this->storableRaw($rawEmail);
        $this->rawParts->persist($stored['split']);

        // Detect BCC map type based on from/to addresses
        $bccMapType = $this->detectBccMapType($fromAddress, $toAddresses);

        // Prepare email data array (reusable for internal emails)
        $emailData = self::toStorableRow([
            'message_id' => $messageId,
            'in_reply_to' => $message->getInReplyTo(),
            'references' => $message->getReferences() ? explode(' ', $message->getReferences()) : null,
            'from_address' => $fromAddress,
            'from_name' => $fromName,
            'to_addresses' => $toAddresses,
            'cc_addresses' => $ccAddresses,
            'bcc_addresses' => null, // BCC is typically not in headers
            'subject' => $message->getSubject() ?: '(No Subject)',
            'body_text' => $message->getTextBody(),
            'headers' => $message->getHeaders()->toArray(),
            'received_at' => $receivedAt,
            'archived_at' => now(),
            'size_bytes' => strlen($rawEmail),
            'hash' => Email::generateHash($rawEmail),
            'is_verified' => true,
            'has_attachments' => $message->hasAttachments(),
            ...$stored['columns'],
        ]);

        // Attachment data (will be attached to both emails if internal)
        $attachmentData = [];
        if ($message->hasAttachments()) {
            $attachments = $message->getAttachments();
            foreach ($attachments as $attachment) {
                $attachmentData[] = [
                    'contents' => $attachment->getContent(),
                    'filename' => $attachment->getName(),
                    'mime_type' => $attachment->getMimeType(),
                    'content_id' => $attachment->getId(),
                    'is_inline' => $attachment->getDisposition() === 'inline',
                ];
            }
        }

        // For internal emails (both sender and recipient are from configured domains),
        // create TWO separate email records - one as 'sender' and one as 'recipient'
        if ($bccMapType === 'both') {
            // Create first email as 'sender' (outgoing)
            $senderEmail = Email::create(array_merge($emailData, [
                'bcc_map_type' => 'sender',
            ]));

            // Store attachments for sender email
            foreach ($attachmentData as $attachment) {
                $this->storeAttachment($senderEmail, $attachment, $stored['split']['decoded_hashes']);
            }

            // Create second email as 'recipient' (incoming)
            // Use a modified message_id to avoid unique constraint violation
            $recipientEmail = Email::create(array_merge($emailData, [
                'message_id' => $messageId.'-recipient',
                'bcc_map_type' => 'recipient',
            ]));

            // Store attachments for recipient email
            foreach ($attachmentData as $attachment) {
                $this->storeAttachment($recipientEmail, $attachment, $stored['split']['decoded_hashes']);
            }

            // Return the sender email (primary record)
            return $senderEmail->fresh('attachments');
        }

        // Regular email (sender OR recipient, not both)
        $email = Email::create(array_merge($emailData, [
            'bcc_map_type' => $bccMapType,
        ]));

        // Store attachments
        foreach ($attachmentData as $attachment) {
            $this->storeAttachment($email, $attachment, $stored['split']['decoded_hashes']);
        }

        return $email->fresh('attachments');
    }

    protected function parseRawEmail(string $rawEmail): array
    {
        $headers = [];
        $body = '';
        $inHeaders = true;
        $lines = explode("\n", $rawEmail);

        foreach ($lines as $line) {
            if ($inHeaders) {
                if (trim($line) === '') {
                    $inHeaders = false;

                    continue;
                }

                if (preg_match('/^([^:]+):\s*(.+)$/', $line, $matches)) {
                    $headerName = $matches[1];
                    $headerValue = trim($matches[2]);
                    $headers[$headerName] = $headerValue;
                }
            } else {
                $body .= $line."\n";
            }
        }

        return [
            'message_id' => $this->extractHeader($headers, 'Message-ID') ?? '<'.Str::uuid().'@mailarchive.local>',
            'in_reply_to' => $this->extractHeader($headers, 'In-Reply-To'),
            'references' => $this->parseReferences($this->extractHeader($headers, 'References')),
            'from_address' => $this->extractEmailAddress($this->extractHeader($headers, 'From', '')),
            'from_name' => $this->extractName($this->extractHeader($headers, 'From', '')),
            'to_addresses' => $this->parseEmailList($this->extractHeader($headers, 'To', '')),
            'cc_addresses' => $this->parseEmailList($this->extractHeader($headers, 'Cc')),
            'bcc_addresses' => $this->parseEmailList($this->extractHeader($headers, 'Bcc')),
            'subject' => $this->extractHeader($headers, 'Subject', '(No Subject)'),
            'body_text' => trim($body),
            'headers' => $headers,
            'received_at' => $this->parseDate($this->extractHeader($headers, 'Date')) ?? now(),
            'attachments' => [],
        ];
    }

    protected function extractHeader(array $headers, string $name, ?string $default = null): ?string
    {
        return $headers[$name] ?? $default;
    }

    protected function extractEmailAddress(string $emailString): string
    {
        if (preg_match('/<([^>]+)>/', $emailString, $matches)) {
            return trim($matches[1]);
        }

        return trim($emailString);
    }

    protected function extractName(string $emailString): ?string
    {
        if (preg_match('/^(.+?)\s*<[^>]+>$/', $emailString, $matches)) {
            return trim($matches[1], ' "\'');
        }

        return null;
    }

    protected function parseEmailList(?string $emailList): ?array
    {
        if (empty($emailList)) {
            return null;
        }

        $emails = [];
        $parts = explode(',', $emailList);

        foreach ($parts as $part) {
            $email = $this->extractEmailAddress(trim($part));
            if (! empty($email)) {
                $emails[] = $email;
            }
        }

        return ! empty($emails) ? $emails : null;
    }

    protected function parseReferences(?string $references): ?array
    {
        if (empty($references)) {
            return null;
        }

        preg_match_all('/<([^>]+)>/', $references, $matches);

        return ! empty($matches[1]) ? $matches[1] : null;
    }

    protected function parseDate(?string $dateString): ?\DateTime
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            return new \DateTime($dateString);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param  array<string, string>  $partHashes  raw part hash => sha256 of what it decodes to
     */
    protected function storeAttachment(Email $email, array $attachmentData, array $partHashes = []): Attachment
    {
        $contents = $attachmentData['contents'];
        // Also what the storage path is built from, so a mail cannot name a
        // file in bytes the file system has to carry afterwards.
        $filename = self::toUtf8($attachmentData['filename']);
        $mimeType = $attachmentData['mime_type'] ?? 'application/octet-stream';
        $size = strlen($contents);

        $hash = Attachment::generateHash($contents);

        // These bytes are already stored: they are the base64 run that was
        // lifted out of this very mail. Point at it rather than writing a
        // second copy - that copy was half of what the archive cost.
        $partHash = array_search($hash, $partHashes, strict: true);

        if ($partHash !== false) {
            return Attachment::create([
                'email_id' => $email->id,
                'filename' => $filename,
                'mime_type' => $mimeType,
                'size_bytes' => $size,
                'hash' => $hash,
                'is_compressed' => false,
                'reference_count' => 1,
                'storage_path' => null,
                'storage_disk' => 'local',
                'raw_part_hash' => $partHash,
                'raw_part_encoding' => 'base64',
                'content_id' => $attachmentData['content_id'] ?? null,
                'is_inline' => $attachmentData['is_inline'] ?? false,
                'extracted_text' => $this->extractTextFrom($contents, $mimeType),
            ]);
        }

        // For large attachments (>5MB), use memory-efficient processing
        $largeAttachmentThreshold = 5 * 1024 * 1024; // 5MB

        if ($size > $largeAttachmentThreshold) {
            return $this->storeLargeAttachment($email, $attachmentData, $contents, $filename, $mimeType, $size);
        }

        // Regular processing for smaller attachments
        $existingAttachment = Attachment::findByHash($hash);

        if ($existingAttachment) {
            $existingAttachment->incrementReferenceCount();

            return Attachment::create([
                'email_id' => $email->id,
                'filename' => $filename,
                'mime_type' => $mimeType,
                'size_bytes' => $existingAttachment->size_bytes,
                'hash' => $hash,
                'is_compressed' => $existingAttachment->is_compressed,
                'reference_count' => 1,
                'storage_path' => $existingAttachment->storage_path,
                'storage_disk' => $existingAttachment->storage_disk,
                'content_id' => $attachmentData['content_id'] ?? null,
                'is_inline' => $attachmentData['is_inline'] ?? false,
            ]);
        }

        $shouldCompress = $this->compression->shouldCompress($size);
        $contentsToStore = $shouldCompress
            ? $this->compression->compress($contents)
            : $contents;

        $storagePath = 'attachments/'.date('Y/m/d').'/'.Str::uuid().'_'.$filename;

        Storage::disk('local')->put($storagePath, $contentsToStore);

        // Extract text from attachment if possible (PDFs, text files)
        $extractedText = null;
        if ($this->textExtractor->canExtract($mimeType)) {
            $fullPath = Storage::disk('local')->path($storagePath);
            $extractedText = self::toUtf8($this->textExtractor->extractText($fullPath, $mimeType));
        }

        return Attachment::create([
            'email_id' => $email->id,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'size_bytes' => $size,
            'hash' => $hash,
            'is_compressed' => $shouldCompress,
            'reference_count' => 1,
            'storage_path' => $storagePath,
            'storage_disk' => 'local',
            'content_id' => $attachmentData['content_id'] ?? null,
            'is_inline' => $attachmentData['is_inline'] ?? false,
            'extracted_text' => $extractedText,
        ]);
    }

    /**
     * Text an attachment can be searched by, pulled out of bytes that have no
     * file of their own yet. The extractors read paths, so it gets one for as
     * long as it takes.
     */
    protected function extractTextFrom(string $contents, string $mimeType): ?string
    {
        if (! $this->textExtractor->canExtract($mimeType)) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'mailarchive-');

        if ($path === false) {
            return null;
        }

        try {
            file_put_contents($path, $contents);

            return self::toUtf8($this->textExtractor->extractText($path, $mimeType));
        } finally {
            @unlink($path);
        }
    }

    /**
     * Store large attachments using memory-efficient streaming
     * Prevents memory exhaustion when processing attachments >5MB
     */
    protected function storeLargeAttachment(
        Email $email,
        array $attachmentData,
        string $contents,
        string $filename,
        string $mimeType,
        int $size
    ): Attachment {
        // Calculate hash for deduplication
        $hash = Attachment::generateHash($contents);

        // Check if this attachment already exists (deduplication)
        $existingAttachment = Attachment::findByHash($hash);

        if ($existingAttachment) {
            // Free memory immediately
            unset($contents);
            gc_collect_cycles();

            $existingAttachment->incrementReferenceCount();

            return Attachment::create([
                'email_id' => $email->id,
                'filename' => $filename,
                'mime_type' => $mimeType,
                'size_bytes' => $existingAttachment->size_bytes,
                'hash' => $hash,
                'is_compressed' => $existingAttachment->is_compressed,
                'reference_count' => 1,
                'storage_path' => $existingAttachment->storage_path,
                'storage_disk' => $existingAttachment->storage_disk,
                'content_id' => $attachmentData['content_id'] ?? null,
                'is_inline' => $attachmentData['is_inline'] ?? false,
            ]);
        }

        // Write directly to storage without keeping in memory
        $storagePath = 'attachments/'.date('Y/m/d').'/'.Str::uuid().'_'.$filename;

        // Determine if we should compress (but don't do it yet for large files)
        $shouldCompress = $this->compression->shouldCompress($size);

        if ($shouldCompress) {
            // Compress in-place and write
            $compressed = $this->compression->compress($contents);
            Storage::disk('local')->put($storagePath, $compressed);

            // Free memory immediately after writing
            unset($contents, $compressed);
            gc_collect_cycles();
        } else {
            // Write uncompressed
            Storage::disk('local')->put($storagePath, $contents);

            // Free memory immediately after writing
            unset($contents);
            gc_collect_cycles();
        }

        // Extract text from attachment if possible (PDFs, text files)
        // This happens AFTER freeing the original content from memory
        $extractedText = null;
        if ($this->textExtractor->canExtract($mimeType)) {
            $fullPath = Storage::disk('local')->path($storagePath);
            $extractedText = self::toUtf8($this->textExtractor->extractText($fullPath, $mimeType));
        }

        \Illuminate\Support\Facades\Log::info('Large attachment stored successfully', [
            'email_id' => $email->id,
            'filename' => $filename,
            'size_mb' => round($size / 1024 / 1024, 2),
            'compressed' => $shouldCompress,
            'storage_path' => $storagePath,
        ]);

        return Attachment::create([
            'email_id' => $email->id,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'size_bytes' => $size,
            'hash' => $hash,
            'is_compressed' => $shouldCompress,
            'reference_count' => 1,
            'storage_path' => $storagePath,
            'storage_disk' => 'local',
            'content_id' => $attachmentData['content_id'] ?? null,
            'is_inline' => $attachmentData['is_inline'] ?? false,
            'extracted_text' => $extractedText,
        ]);
    }

    /**
     * Detect BCC map type based on sender and recipient addresses
     *
     * @param  string|null  $fromAddress  Sender email address
     * @param  array|null  $toAddresses  Recipient email addresses
     * @return string|null 'sender', 'recipient', 'both', or null
     */
    protected function detectBccMapType(?string $fromAddress, ?array $toAddresses): ?string
    {
        if (! $fromAddress && ! $toAddresses) {
            return null;
        }

        // Extract domain from from_address
        $fromDomain = $fromAddress ? $this->extractDomain($fromAddress) : null;

        // Extract domains from to_addresses
        $toDomains = [];
        if ($toAddresses) {
            foreach ($toAddresses as $toAddress) {
                $domain = $this->extractDomain($toAddress);
                if ($domain) {
                    $toDomains[] = $domain;
                }
            }
        }

        // Get configured domains from all active IMAP accounts
        $configuredDomains = \App\Models\ImapAccount::where('is_active', true)
            ->get()
            ->map(fn ($account) => $this->extractDomain($account->username))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($configuredDomains)) {
            return null;
        }

        // Check if sender is from configured domain (Sender Map)
        $isSender = $fromDomain && in_array($fromDomain, $configuredDomains);

        // Check if any recipient is from configured domain (Recipient Map)
        $isRecipient = ! empty(array_intersect($toDomains, $configuredDomains));

        // Determine BCC map type
        if ($isSender && $isRecipient) {
            return 'both';
        } elseif ($isSender) {
            return 'sender';
        } elseif ($isRecipient) {
            return 'recipient';
        }

        return null;
    }

    /**
     * Extract domain from email address
     */
    protected function extractDomain(string $email): ?string
    {
        if (preg_match('/@([^@]+)$/', $email, $matches)) {
            return strtolower(trim($matches[1]));
        }

        return null;
    }
}
