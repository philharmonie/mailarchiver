<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Email;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmailParserService
{
    public function __construct(
        protected CompressionService $compression
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
        ]);
    }
}
