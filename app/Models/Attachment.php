<?php

namespace App\Models;

use App\Services\CompressionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    /** @use HasFactory<\Database\Factories\AttachmentFactory> */
    use HasFactory;

    protected $fillable = [
        'email_id',
        'filename',
        'mime_type',
        'size_bytes',
        'hash',
        'is_compressed',
        'reference_count',
        'storage_path',
        'storage_disk',
        'content_id',
        'is_inline',
        'extracted_text',
    ];

    protected function casts(): array
    {
        return [
            'is_inline' => 'boolean',
            'is_compressed' => 'boolean',
        ];
    }

    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function getContents(): string
    {
        $contents = Storage::disk($this->storage_disk)->get($this->storage_path);

        if ($this->is_compressed) {
            return app(CompressionService::class)->decompress($contents);
        }

        return $contents;
    }

    public function verifyHash(): bool
    {
        $contents = $this->getContents();
        $calculatedHash = hash('sha256', $contents);

        return $calculatedHash === $this->hash;
    }

    public function incrementReferenceCount(): void
    {
        $this->increment('reference_count');
    }

    public function decrementReferenceCount(): bool
    {
        if ($this->reference_count > 0) {
            $this->decrement('reference_count');

            if ($this->reference_count === 0) {
                $this->deleteFile();
                $this->delete();

                return true;
            }
        }

        return false;
    }

    public function deleteFile(): void
    {
        if (Storage::disk($this->storage_disk)->exists($this->storage_path)) {
            Storage::disk($this->storage_disk)->delete($this->storage_path);
        }
    }

    public static function generateHash(string $contents): string
    {
        return hash('sha256', $contents);
    }

    public static function findByHash(string $hash): ?self
    {
        return self::where('hash', $hash)->first();
    }
}
