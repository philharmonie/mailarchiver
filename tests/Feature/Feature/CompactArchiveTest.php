<?php

use App\Models\Attachment;
use App\Models\Email;
use App\Models\RawPart;
use App\Services\CompressionService;
use App\Services\EmailParserService;
use Illuminate\Support\Facades\Storage;

/**
 * A mail archived the old way: everything inline in raw_email, the attachment
 * kept a second time as a file of its own.
 */
function archiveTheOldWay(string $payload, string $messageId): Email
{
    $raw = mailWithAttachment($payload, $messageId);

    $email = app(EmailParserService::class)->parseAndStoreFromImap(
        imapMessageFor($raw, $payload, $messageId)
    );

    // Undo the new storage so the row looks like one written before it.
    $compression = app(CompressionService::class);
    $email->forceFill([
        'raw_email' => $compression->compress($raw),
        'is_compressed' => true,
        'raw_parts' => null,
    ])->saveQuietly();

    $path = 'attachments/old/'.$messageId.'.bin';
    Storage::disk('local')->put($path, $payload);

    $email->attachments()->update([
        'raw_part_hash' => null,
        'raw_part_encoding' => null,
        'storage_path' => $path,
        'storage_disk' => 'local',
        'is_compressed' => false,
    ]);

    RawPart::query()->delete();

    return $email->fresh();
}

test('compacting lifts the attachment out and keeps the mail intact', function () {
    $payload = attachmentPayload();
    $email = archiveTheOldWay($payload, '<old@example.com>');
    $raw = $email->getRawEmailDecompressed();

    $this->artisan('archive:compact')->assertSuccessful();

    $email = $email->fresh();

    expect($email->raw_parts)->toHaveCount(1)
        ->and($email->getRawEmailDecompressed())->toBe($raw)
        ->and($email->verifyHashWithDecompression())->toBeTrue();
});

test('compacting takes away the second copy of the bytes', function () {
    $payload = attachmentPayload();
    $email = archiveTheOldWay($payload, '<old@example.com>');
    $oldPath = $email->attachments()->first()->storage_path;

    $this->artisan('archive:compact')->assertSuccessful();

    $attachment = Attachment::first();

    expect(Storage::disk('local')->exists($oldPath))->toBeFalse()
        ->and($attachment->storage_path)->toBeNull()
        ->and($attachment->raw_part_hash)->not->toBeNull()
        // Still the same bytes, now read out of the mail itself.
        ->and($attachment->getContents())->toBe($payload)
        ->and($attachment->verifyHash())->toBeTrue();
});

test('a dry run writes nothing', function () {
    $payload = attachmentPayload();
    $email = archiveTheOldWay($payload, '<old@example.com>');
    $oldPath = $email->attachments()->first()->storage_path;

    $this->artisan('archive:compact --dry-run')->assertSuccessful();

    expect($email->fresh()->raw_parts)->toBeNull()
        ->and(RawPart::count())->toBe(0)
        ->and(Storage::disk('local')->exists($oldPath))->toBeTrue();
});

test('a second run finds nothing left to do', function () {
    archiveTheOldWay(attachmentPayload(), '<old@example.com>');

    $this->artisan('archive:compact')->assertSuccessful();
    $this->artisan('archive:compact')->expectsOutputToContain('Nothing left to compact.');
});

test('mail whose stored bytes no longer match its hash is left alone', function () {
    $email = archiveTheOldWay(attachmentPayload(), '<old@example.com>');
    $email->forceFill(['hash' => str_repeat('0', 64)])->saveQuietly();

    $this->artisan('archive:compact')->assertFailed();

    expect($email->fresh()->raw_parts)->toBeNull()
        ->and(RawPart::count())->toBe(0);
});
