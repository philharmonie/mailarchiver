<?php

use App\Models\Attachment;
use App\Models\Email;
use App\Models\RawPart;
use App\Services\EmailParserService;
use App\Services\RawEmailStore;
use Illuminate\Support\Facades\Storage;

test('the mail comes back byte for byte after its attachment is lifted out', function () {
    $raw = mailWithAttachment(attachmentPayload());

    $email = app(EmailParserService::class)->parseAndStore($raw);

    expect($email->raw_parts)->toHaveCount(1)
        ->and($email->getRawEmailDecompressed())->toBe($raw)
        ->and($email->verifyHashWithDecompression())->toBeTrue();
});

test('what stays in the column no longer holds the attachment', function () {
    $payload = attachmentPayload();
    $raw = mailWithAttachment($payload);

    $email = app(EmailParserService::class)->parseAndStore($raw);
    $stored = $email->is_compressed
        ? gzdecode($email->raw_email)
        : $email->raw_email;

    expect(strlen($stored))->toBeLessThan(strlen($raw) / 10)
        ->and(RawEmailStore::looksSplit($stored))->toBeTrue()
        ->and($stored)->not->toContain(substr(base64_encode($payload), 0, 76));
});

test('two mails carrying the same attachment share one part', function () {
    $payload = attachmentPayload();
    $parser = app(EmailParserService::class);

    $parser->parseAndStore(mailWithAttachment($payload, '<first@example.com>'));
    $parser->parseAndStore(mailWithAttachment($payload, '<second@example.com>'));

    expect(RawPart::count())->toBe(1)
        ->and(RawPart::first()->reference_count)->toBe(2)
        ->and(Email::count())->toBe(2);
});

test('a mail without attachments is left whole', function () {
    $raw = "From: sender@example.com\r\nTo: recipient@example.com\r\nSubject: Plain\r\n\r\nNothing attached.\r\n";

    $email = app(EmailParserService::class)->parseAndStore($raw);

    expect($email->raw_parts)->toBe([])
        ->and(RawPart::count())->toBe(0)
        ->and($email->getRawEmailDecompressed())->toBe($raw);
});

test('the archive stores the attachment bytes once, not twice', function () {
    $payload = attachmentPayload();

    // The attachments are pulled off the IMAP message, so this one goes in the
    // way a synced mail does.
    app(EmailParserService::class)->parseAndStoreFromImap(
        imapMessageFor(mailWithAttachment($payload), $payload)
    );

    $attachment = Attachment::first();

    // No file of its own - and it still hands back exactly what was sent.
    expect($attachment->storage_path)->toBeNull()
        ->and($attachment->raw_part_hash)->not->toBeNull()
        ->and($attachment->getContents())->toBe($payload)
        ->and($attachment->verifyHash())->toBeTrue()
        ->and(Storage::disk('local')->files('attachments'))->toBeEmpty();
});

test('a run too small to be worth a file stays in the mail', function () {
    $raw = mailWithAttachment(attachmentPayload(bytes: 512));

    $email = app(EmailParserService::class)->parseAndStore($raw);

    expect($email->raw_parts)->toBe([])
        ->and($email->getRawEmailDecompressed())->toBe($raw);
});

test('releasing the last mail holding a part takes the file with it', function () {
    $payload = attachmentPayload();
    $parser = app(EmailParserService::class);

    $parser->parseAndStore(mailWithAttachment($payload, '<first@example.com>'));
    $parser->parseAndStore(mailWithAttachment($payload, '<second@example.com>'));

    $store = app(RawEmailStore::class);
    $hash = RawPart::first()->hash;
    $path = RawPart::first()->storage_path;

    $store->release([$hash]);
    expect(RawPart::where('hash', $hash)->exists())->toBeTrue()
        ->and(Storage::disk('local')->exists($path))->toBeTrue();

    $store->release([$hash]);
    expect(RawPart::where('hash', $hash)->exists())->toBeFalse()
        ->and(Storage::disk('local')->exists($path))->toBeFalse();
});
