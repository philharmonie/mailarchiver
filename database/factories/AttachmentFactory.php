<?php

namespace Database\Factories;

use App\Models\Email;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mimeTypes = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        ];

        $mimeType = fake()->randomElement(array_keys($mimeTypes));
        $extension = $mimeTypes[$mimeType];
        $filename = fake()->word().'.'.$extension;
        $contents = fake()->text(1000);

        return [
            'email_id' => Email::factory(),
            'filename' => $filename,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($contents),
            'hash' => hash('sha256', $contents),
            'storage_path' => 'attachments/'.fake()->uuid().'_'.$filename,
            'storage_disk' => 'local',
            'content_id' => null,
            'is_inline' => false,
        ];
    }

    public function inline(): static
    {
        return $this->state(fn (array $attributes) => [
            'content_id' => '<'.fake()->uuid().'@'.fake()->domainName().'>',
            'is_inline' => true,
        ]);
    }
}
