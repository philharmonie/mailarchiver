<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Email;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webklex\PHPIMAP\Message;

class EmailParserService
{
    public function __construct(
        protected CompressionService $compression,
        protected TextExtractorService $textExtractor
    ) {}

    public function parseAndStore(string $rawEmail): Email
    {
        $parsed = $this->parseRawEmail($rawEmail);

        $shouldCompress = $this->compression->shouldCompress(strlen($rawEmail));
        $rawEmailToStore = $shouldCompress
            ? $this->compression->compress($rawEmail)
            : $rawEmail;

        $email = Email::create([
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
            'body_html' => $parsed['body_html'],
            'headers' => $parsed['headers'],
            'received_at' => $parsed['received_at'],
            'archived_at' => now(),
            'size_bytes' => strlen($rawEmail),
            'hash' => Email::generateHash($rawEmail),
            'is_verified' => true,
            'is_compressed' => $shouldCompress,
            'raw_email' => $rawEmailToStore,
            'has_attachments' => ! empty($parsed['attachments']),
        ]);

        foreach ($parsed['attachments'] ?? [] as $attachmentData) {
            $this->storeAttachment($email, $attachmentData);
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

        // Get raw email for hash and storage
        $rawEmail = $message->getRawBody();

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
        $receivedAt = $dateHeader ? $dateHeader->toDate() : now();

        $shouldCompress = $this->compression->shouldCompress(strlen($rawEmail));
        $rawEmailToStore = $shouldCompress
            ? $this->compression->compress($rawEmail)
            : $rawEmail;

        // Detect BCC map type based on from/to addresses
        $bccMapType = $this->detectBccMapType($fromAddress, $toAddresses);

        // Prepare email data array (reusable for internal emails)
        $emailData = [
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
            'body_html' => $message->getHTMLBody(),
            'headers' => $message->getHeaders()->toArray(),
            'received_at' => $receivedAt,
            'archived_at' => now(),
            'size_bytes' => strlen($rawEmail),
            'hash' => Email::generateHash($rawEmail),
            'is_verified' => true,
            'is_compressed' => $shouldCompress,
            'raw_email' => $rawEmailToStore,
            'has_attachments' => $message->hasAttachments(),
        ];

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
                $this->storeAttachment($senderEmail, $attachment);
            }

            // Create second email as 'recipient' (incoming)
            // Use a modified message_id to avoid unique constraint violation
            $recipientEmail = Email::create(array_merge($emailData, [
                'message_id' => $messageId.'-recipient',
                'bcc_map_type' => 'recipient',
            ]));

            // Store attachments for recipient email
            foreach ($attachmentData as $attachment) {
                $this->storeAttachment($recipientEmail, $attachment);
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
            $this->storeAttachment($email, $attachment);
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
            'body_html' => null,
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

    protected function storeAttachment(Email $email, array $attachmentData): Attachment
    {
        $contents = $attachmentData['contents'];
        $filename = $attachmentData['filename'];
        $mimeType = $attachmentData['mime_type'] ?? 'application/octet-stream';
        $hash = Attachment::generateHash($contents);

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

        $shouldCompress = $this->compression->shouldCompress(strlen($contents));
        $contentsToStore = $shouldCompress
            ? $this->compression->compress($contents)
            : $contents;

        $storagePath = 'attachments/'.date('Y/m/d').'/'.Str::uuid().'_'.$filename;

        Storage::disk('local')->put($storagePath, $contentsToStore);

        // Extract text from attachment if possible (PDFs, text files)
        $extractedText = null;
        if ($this->textExtractor->canExtract($mimeType)) {
            $fullPath = Storage::disk('local')->path($storagePath);
            $extractedText = $this->textExtractor->extractText($fullPath, $mimeType);
        }

        return Attachment::create([
            'email_id' => $email->id,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($contents),
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
