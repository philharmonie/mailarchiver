<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ImapAccount>
 */
class ImapAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' Mail Archive',
            'host' => $this->faker->domainName(),
            'port' => 993,
            'encryption' => 'ssl',
            'validate_cert' => true,
            'username' => $this->faker->email(),
            'password' => encrypt('password'),
            'folder' => 'INBOX',
            'is_active' => true,
            'last_fetch_at' => null,
            'total_emails' => 0,
            'total_size_bytes' => 0,
            'sync_interval' => $this->faker->randomElement(['every_15_minutes', 'hourly', 'every_6_hours', 'daily', 'weekly']),
            'delete_after_archive' => false,
            'last_sync_at' => null,
        ];
    }
}
