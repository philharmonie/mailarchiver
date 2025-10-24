<?php

use App\Services\EmailParserService;

use function Pest\Laravel\assertDatabaseHas;

test('email parser receives and archives email', function () {
    $rawEmail = <<<'EMAIL'
From: sender@example.com
To: recipient@example.com
Subject: Test Email
Date: Mon, 23 Oct 2025 10:00:00 +0000

This is a test email body.
EMAIL;

    $parser = app(EmailParserService::class);
    $email = $parser->parseAndStore($rawEmail);

    expect($email)->not->toBeNull()
        ->and($email->from_address)->toBe('sender@example.com')
        ->and($email->subject)->toBe('Test Email');

    assertDatabaseHas('emails', [
        'from_address' => 'sender@example.com',
        'subject' => 'Test Email',
    ]);
});

test('email parser extracts all fields correctly', function () {
    $rawEmail = <<<'EMAIL'
Message-ID: <test-12345@example.com>
From: John Doe <john@example.com>
To: jane@example.com
Cc: bob@example.com
Subject: Important Meeting
Date: Mon, 23 Oct 2025 14:30:00 +0000

Meeting at 3pm tomorrow.
EMAIL;

    $parser = app(EmailParserService::class);
    $email = $parser->parseAndStore($rawEmail);

    expect($email->message_id)->toBe('<test-12345@example.com>')
        ->and($email->from_address)->toBe('john@example.com')
        ->and($email->from_name)->toBe('John Doe')
        ->and($email->to_addresses)->toBe(['jane@example.com'])
        ->and($email->cc_addresses)->toBe(['bob@example.com'])
        ->and($email->subject)->toBe('Important Meeting')
        ->and($email->body_text)->toContain('Meeting at 3pm');
});

test('email is compressed when larger than threshold', function () {
    $longBody = str_repeat('This is a long email body. ', 100);
    $rawEmail = "From: sender@example.com\nTo: recipient@example.com\nSubject: Long Email\n\n{$longBody}";

    $parser = app(EmailParserService::class);
    $email = $parser->parseAndStore($rawEmail);

    expect($email->is_compressed)->toBeTrue()
        ->and(strlen($email->raw_email))->toBeLessThan(strlen($rawEmail));
});

test('email hash is generated and verified', function () {
    $rawEmail = "From: sender@example.com\nTo: recipient@example.com\nSubject: Test\n\nBody";

    $parser = app(EmailParserService::class);
    $email = $parser->parseAndStore($rawEmail);

    expect($email->hash)->not->toBeEmpty()
        ->and($email->is_verified)->toBeTrue()
        ->and($email->verifyHashWithDecompression())->toBeTrue();
});

// Frontend tests require compiled Vite assets - run `npm run build` first
