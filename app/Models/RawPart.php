<?php

namespace App\Models;

use App\Services\CompressionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * One base64 run lifted out of a mail, stored once. See the raw_parts
 * migration for why.
 */
class RawPart extends Model
{
    protected $fillable = [
        'hash',
        'size_bytes',
        'storage_path',
        'storage_disk',
        'is_compressed',
        'reference_count',
    ];

    protected function casts(): array
    {
        return [
            'is_compressed' => 'boolean',
        ];
    }

    /**
     * The encoded bytes, exactly as they stood in the mail.
     */
    public function contents(): string
    {
        $contents = Storage::disk($this->storage_disk)->get($this->storage_path);

        if ($contents === null) {
            throw new \RuntimeException("Raw part {$this->hash} is missing from {$this->storage_disk}");
        }

        return $this->is_compressed
            ? app(CompressionService::class)->decompress($contents)
            : $contents;
    }

    /**
     * What the part decodes to - the attachment's own bytes.
     */
    public function decoded(): string
    {
        return base64_decode($this->contents(), strict: false);
    }

    public function release(): void
    {
        $this->decrement('reference_count');

        if ($this->fresh()?->reference_count > 0) {
            return;
        }

        Storage::disk($this->storage_disk)->delete($this->storage_path);
        $this->delete();
    }
}
