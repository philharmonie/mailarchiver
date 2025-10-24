<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImapAccount extends Model
{
    /** @use HasFactory<\Database\Factories\ImapAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'host',
        'port',
        'encryption',
        'validate_cert',
        'username',
        'password',
        'folder',
        'is_active',
        'delete_after_archive',
        'sync_interval',
        'last_sync_at',
        'last_fetch_at',
        'total_emails',
        'total_size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'validate_cert' => 'boolean',
            'is_active' => 'boolean',
            'delete_after_archive' => 'boolean',
            'last_sync_at' => 'datetime',
            'last_fetch_at' => 'datetime',
            'total_emails' => 'integer',
            'total_size_bytes' => 'integer',
            'password' => 'encrypted',
        ];
    }

    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }

    public function incrementStats(int $emailCount, int $sizeBytes): void
    {
        $this->increment('total_emails', $emailCount);
        $this->increment('total_size_bytes', $sizeBytes);
        $this->update(['last_fetch_at' => now()]);
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->total_size_bytes;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2).' MB';
        }

        return round($bytes / (1024 * 1024 * 1024), 2).' GB';
    }
}
