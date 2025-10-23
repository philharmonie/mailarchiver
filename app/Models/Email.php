<?php

namespace App\Models;

use App\Services\CompressionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;

class Email extends Model
{
    /** @use HasFactory<\Database\Factories\EmailFactory> */
    use HasFactory, Searchable;

    protected $fillable = [
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
        'body_html',
        'headers',
        'received_at',
        'size_bytes',
        'hash',
        'is_verified',
        'is_compressed',
        'raw_email',
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
            'received_at' => 'datetime',
            'is_verified' => 'boolean',
            'is_compressed' => 'boolean',
            'has_attachments' => 'boolean',
            'is_archived' => 'boolean',
        ];
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function verifyHash(): bool
    {
        $calculatedHash = hash('sha256', $this->raw_email);

        return $calculatedHash === $this->hash;
    }

    public static function generateHash(string $rawEmail): string
    {
        return hash('sha256', $rawEmail);
    }

    public function getRawEmailDecompressed(): string
    {
        if ($this->is_compressed) {
            return app(CompressionService::class)->decompress($this->raw_email);
        }

        return $this->raw_email;
    }

    public function verifyHashWithDecompression(): bool
    {
        $rawEmail = $this->getRawEmailDecompressed();
        $calculatedHash = hash('sha256', $rawEmail);

        return $calculatedHash === $this->hash;
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
