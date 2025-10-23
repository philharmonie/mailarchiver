<?php

namespace Database\Factories;

use App\Models\Email;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $actions = ['viewed', 'exported', 'searched', 'downloaded_attachment', 'verified_hash'];

        return [
            'auditable_type' => Email::class,
            'auditable_id' => Email::factory(),
            'user_id' => User::factory(),
            'action' => fake()->randomElement($actions),
            'description' => fake()->sentence(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'metadata' => [
                'browser' => fake()->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
                'platform' => fake()->randomElement(['Windows', 'macOS', 'Linux']),
            ],
        ];
    }

    public function forEmail(Email $email): static
    {
        return $this->state(fn (array $attributes) => [
            'auditable_type' => Email::class,
            'auditable_id' => $email->id,
        ]);
    }

    public function action(string $action): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => $action,
        ]);
    }
}
