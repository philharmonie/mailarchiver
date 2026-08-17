<?php

namespace App\Models;

use App\Services\CompressionService;
use App\Services\EmailParserService;
use App\Services\RawEmailStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Searchable;
use Webklex\PHPIMAP\Message as ImapMessage;

class Email extends Model
{
    /** @use HasFactory<\Database\Factories\EmailFactory> */
    use HasFactory, Searchable;

    protected $hidden = [
        'raw_email',  // Contains binary/compressed data, not UTF-8 safe
        'raw_parts',  // Bookkeeping, see RawEmailStore
        'body_text',  // Large field, not needed in list views
        'headers',    // Large array, not needed in list views
    ];

    protected $fillable = [
        'imap_account_id',
        'bcc_map_type',
        'message_id',
        'in_reply_to',
        'references',
        'from_address',
        'from_name',
        'to_addresses',
        'cc_addresses',
        'bcc_addresses',
        'subject',
        'body_text',
        'headers',
        'received_at',
        'archived_at',
        'size_bytes',
        'hash',
        'is_verified',
        'is_compressed',
        'raw_email',
        'raw_parts',
        'has_attachments',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'references' => 'array',
            'to_addresses' => 'array',
            'cc_addresses' => 'array',
            'bcc_addresses' => 'array',
            'headers' => 'array',
            'raw_parts' => 'array',
            'received_at' => 'datetime',
            'archived_at' => 'datetime',
            'is_verified' => 'boolean',
            'is_compressed' => 'boolean',
            'has_attachments' => 'boolean',
            'is_archived' => 'boolean',
        ];
    }

    public function imapAccount(): BelongsTo
    {
        return $this->belongsTo(ImapAccount::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->orderByDesc('created_at');
    }

    /**
     * Whether the archived mail is still the mail that was archived.
     *
     * Over the mail itself, never over the column: what is stored may be
     * compressed, and may be a skeleton whose attachments live in raw parts.
     * The hash has always been taken over the original.
     */
    public function verifyHash(): bool
    {
        return $this->verifyHashWithDecompression();
    }

    public static function generateHash(string $rawEmail): string
    {
        return hash('sha256', $rawEmail);
    }

    /**
     * The mail as it arrived.
     *
     * What the column holds may be a skeleton: the attachments of a mail are
     * stored once, as raw parts, and put back here. See RawEmailStore.
     */
    public function getRawEmailDecompressed(): string
    {
        $stored = $this->is_compressed
            ? app(CompressionService::class)->decompress($this->raw_email)
            : $this->raw_email;

        return app(RawEmailStore::class)->assemble($stored);
    }

    public function verifyHashWithDecompression(): bool
    {
        $rawEmail = $this->getRawEmailDecompressed();
        $calculatedHash = hash('sha256', $rawEmail);

        return $calculatedHash === $this->hash;
    }

    /**
     * The HTML body, read back out of the mail itself.
     *
     * It is no longer stored: `raw_email` already holds it, and holding it
     * twice cost 385 MB across the first 13.000 mails. Nothing searches HTML -
     * search runs on body_text - so it is only ever wanted for the single mail
     * somebody has open, which is cheap enough to parse on the spot.
     *
     * Null for mail without an HTML part, and for mail this parser cannot make
     * sense of; the view falls back to the text body either way.
     */
    public function deriveBodyHtml(): ?string
    {
        try {
            $html = ImapMessage::fromString($this->getRawEmailDecompressed())->getHTMLBody();
        } catch (\Throwable $e) {
            Log::warning('Could not read the HTML body out of an archived mail', [
                'email_id' => $this->id,
                'error' => EmailParserService::errorExcerpt($e),
            ]);

            return null;
        }

        return is_string($html) && $html !== ''
            ? EmailParserService::toUtf8($html)
            : null;
    }

    public function shouldBeSearchable(): bool
    {
        return config('scout.driver') !== null;
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'message_id' => $this->message_id,
            'from_address' => $this->from_address,
            'from_name' => $this->from_name,
            'to_addresses' => $this->to_addresses,
            'cc_addresses' => $this->cc_addresses,
            'subject' => $this->subject,
            'body_text' => $this->body_text,
            'received_at' => $this->received_at?->timestamp,
        ];
    }
}
