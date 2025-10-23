<?php

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Email;
use App\Services\CompressionService;

use function Pest\Laravel\actingAs;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('compression service compresses and decompresses data', function () {
    $compression = new CompressionService;
    $originalData = str_repeat('This is some test data that should be compressed. ', 50);

    $compressed = $compression->compress($originalData);
    $decompressed = $compression->decompress($compressed);

    expect($compressed)->not->toBe($originalData)
        ->and(strlen($compressed))->toBeLessThan(strlen($originalData))
        ->and($decompressed)->toBe($originalData);
});

test('compression service calculates compression ratio', function () {
    $compression = new CompressionService;
    $originalData = str_repeat('test data ', 100);

    $compressed = $compression->compress($originalData);
    $ratio = $compression->getCompressionRatio($originalData, $compressed);

    expect($ratio)->toBeGreaterThan(0);
});

test('email hash verification works correctly', function () {
    $rawEmail = 'From: test@example.com\nSubject: Test\n\nBody';
    $email = Email::factory()->create([
        'raw_email' => $rawEmail,
        'hash' => Email::generateHash($rawEmail),
        'is_compressed' => false,
    ]);

    expect($email->verifyHash())->toBeTrue();
});

test('email hash verification fails with tampered data', function () {
    $rawEmail = 'From: test@example.com\nSubject: Test\n\nBody';
    $email = Email::factory()->create([
        'raw_email' => 'Tampered email',
        'hash' => Email::generateHash($rawEmail),
        'is_compressed' => false,
    ]);

    expect($email->verifyHash())->toBeFalse();
});

test('compressed email hash verification works', function () {
    $rawEmail = str_repeat('From: test@example.com\nSubject: Test\n\nBody ', 50);
    $compression = new CompressionService;

    $email = Email::factory()->create([
        'raw_email' => $compression->compress($rawEmail),
        'hash' => Email::generateHash($rawEmail),
        'is_compressed' => true,
    ]);

    expect($email->verifyHashWithDecompression())->toBeTrue();
});

test('attachment deduplication works correctly', function () {
    $contents = 'Test file contents';
    $hash = Attachment::generateHash($contents);

    $email1 = Email::factory()->create();
    $email2 = Email::factory()->create();

    $attachment1 = Attachment::factory()->create([
        'email_id' => $email1->id,
        'hash' => $hash,
        'storage_path' => 'test/path1.txt',
        'reference_count' => 1,
    ]);

    $existingAttachment = Attachment::findByHash($hash);

    expect($existingAttachment->id)->toBe($attachment1->id);

    $attachment2 = Attachment::factory()->create([
        'email_id' => $email2->id,
        'hash' => $hash,
        'storage_path' => $attachment1->storage_path,
        'reference_count' => 1,
    ]);

    $attachment1->refresh();
    expect(Attachment::where('hash', $hash)->count())->toBe(2)
        ->and($attachment1->storage_path)->toBe($attachment2->storage_path);
});

test('audit log creation works correctly', function () {
    $email = Email::factory()->create();
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user);

    $log = AuditLog::log($email, 'viewed', 'Email was viewed');

    expect($log->auditable_id)->toBe($email->id)
        ->and($log->auditable_type)->toBe(Email::class)
        ->and($log->action)->toBe('viewed')
        ->and($log->user_id)->toBe($user->id);
});

test('attachment reference count increments and decrements', function () {
    $attachment = Attachment::factory()->create(['reference_count' => 1]);

    $attachment->incrementReferenceCount();
    expect($attachment->fresh()->reference_count)->toBe(2);

    $attachment->decrementReferenceCount();
    expect($attachment->fresh()->reference_count)->toBe(1);
});
