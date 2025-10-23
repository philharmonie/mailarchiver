<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Email>
 */
class EmailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fromEmail = fake()->safeEmail();
        $subject = fake()->sentence();
        $bodyText = fake()->paragraphs(3, true);
        $bodyHtml = '<p>'.implode('</p><p>', fake()->paragraphs(3)).'</p>';

        $rawEmail = "From: {$fromEmail}\nSubject: {$subject}\n\n{$bodyText}";

        return [
            'message_id' => '<'.fake()->uuid().'@'.fake()->domainName().'>',
            'in_reply_to' => null,
            'references' => null,
            'from_address' => $fromEmail,
            'from_name' => fake()->name(),
            'to_addresses' => [fake()->safeEmail()],
            'cc_addresses' => fake()->boolean(30) ? [fake()->safeEmail()] : null,
            'bcc_addresses' => null,
            'subject' => $subject,
            'body_text' => $bodyText,
            'body_html' => $bodyHtml,
            'headers' => [
                'Return-Path' => $fromEmail,
                'Content-Type' => 'text/html; charset=UTF-8',
                'MIME-Version' => '1.0',
            ],
            'received_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'size_bytes' => strlen($rawEmail),
            'hash' => hash('sha256', $rawEmail),
            'is_verified' => true,
            'raw_email' => $rawEmail,
            'has_attachments' => false,
            'is_archived' => fake()->boolean(20),
        ];
    }

    public function withAttachments(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_attachments' => true,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_archived' => true,
        ]);
    }
}
