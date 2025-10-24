<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'is_encrypted'];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    /**
     * Get a setting value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = self::where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        if ($setting->is_encrypted) {
            return Crypt::decryptString($setting->value);
        }

        return $setting->value;
    }

    /**
     * Set a setting value
     */
    public static function set(string $key, mixed $value, bool $encrypted = false): self
    {
        $valueToStore = $encrypted ? Crypt::encryptString($value) : $value;

        return self::updateOrCreate(
            ['key' => $key],
            ['value' => $valueToStore, 'is_encrypted' => $encrypted]
        );
    }

    /**
     * Check if Mailcow is enabled
     */
    public static function isMailcowEnabled(): bool
    {
        return (bool) self::get('mailcow_enabled', false);
    }

    /**
     * Get Mailcow API URL
     */
    public static function getMailcowApiUrl(): ?string
    {
        return self::get('mailcow_api_url');
    }

    /**
     * Get Mailcow API Key
     */
    public static function getMailcowApiKey(): ?string
    {
        return self::get('mailcow_api_key');
    }
}
