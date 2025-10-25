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

test('internal email creates two separate records (sender and recipient)', function () {
    // Create IMAP account for domain detection
    $account = \App\Models\ImapAccount::factory()->create([
        'username' => 'archive@testdomain.com',
        'is_active' => true,
    ]);

    // Create internal email (both sender and recipient from same domain)
    $message = \Mockery::mock(\Webklex\PHPIMAP\Message::class);
    $message->shouldReceive('getMessageId')->andReturn('<internal-test@testdomain.com>');
    $message->shouldReceive('getRawBody')->andReturn('From: user1@testdomain.com...');

    // Mock header object
    $header = \Mockery::mock(\Webklex\PHPIMAP\Header::class);
    $header->raw = "From: user1@testdomain.com\r\nTo: user2@testdomain.com\r\nSubject: Internal Test Email\r\n";
    $message->shouldReceive('getHeader')->andReturn($header);
    $message->shouldReceive('getFrom')->andReturn(collect([
        (object) ['mail' => 'user1@testdomain.com', 'personal' => 'User One'],
    ]));
    $message->shouldReceive('getTo')->andReturn(collect([
        (object) ['mail' => 'user2@testdomain.com'],
    ]));
    $message->shouldReceive('getCc')->andReturn(null);
    $message->shouldReceive('getDate')->andReturn(now());
    $message->shouldReceive('getInReplyTo')->andReturn(null);
    $message->shouldReceive('getReferences')->andReturn(null);
    $message->shouldReceive('getSubject')->andReturn('Internal Test Email');
    $message->shouldReceive('getTextBody')->andReturn('Internal email body');
    $message->shouldReceive('getHTMLBody')->andReturn('');
    $message->shouldReceive('getHeaders')->andReturn(collect()->put('Date', now()->toRfc2822String()));
    $message->shouldReceive('hasAttachments')->andReturn(false);

    $parser = app(EmailParserService::class);
    $email = $parser->parseAndStoreFromImap($message);

    // Should create sender email
    expect($email)->not->toBeNull()
        ->and($email->bcc_map_type)->toBe('sender')
        ->and($email->message_id)->toBe('<internal-test@testdomain.com>');

    // Should also create recipient email
    assertDatabaseHas('emails', [
        'message_id' => '<internal-test@testdomain.com>-recipient',
        'bcc_map_type' => 'recipient',
        'subject' => 'Internal Test Email',
    ]);

    // Both should have same hash
    $recipientEmail = \App\Models\Email::where('message_id', '<internal-test@testdomain.com>-recipient')->first();
    expect($recipientEmail->hash)->toBe($email->hash);

    // Should have exactly 2 emails with this subject
    expect(\App\Models\Email::where('subject', 'Internal Test Email')->count())->toBe(2);
});

test('external sender email creates only sender record', function () {
    // Create IMAP account
    $account = \App\Models\ImapAccount::factory()->create([
        'username' => 'archive@testdomain.com',
        'is_active' => true,
    ]);

    $message = \Mockery::mock(\Webklex\PHPIMAP\Message::class);
    $message->shouldReceive('getMessageId')->andReturn('<sender-test@testdomain.com>');
    $message->shouldReceive('getRawBody')->andReturn('From: sender@testdomain.com...');

    // Mock header object
    $header = \Mockery::mock(\Webklex\PHPIMAP\Header::class);
    $header->raw = "From: sender@testdomain.com\r\nTo: external@example.com\r\nSubject: Sender Test\r\n";
    $message->shouldReceive('getHeader')->andReturn($header);
    $message->shouldReceive('getFrom')->andReturn(collect([
        (object) ['mail' => 'sender@testdomain.com', 'personal' => null],
    ]));
    $message->shouldReceive('getTo')->andReturn(collect([
        (object) ['mail' => 'external@example.com'],
    ]));
    $message->shouldReceive('getCc')->andReturn(null);
    $message->shouldReceive('getDate')->andReturn(now());
    $message->shouldReceive('getInReplyTo')->andReturn(null);
    $message->shouldReceive('getReferences')->andReturn(null);
    $message->shouldReceive('getSubject')->andReturn('Outgoing Email');
    $message->shouldReceive('getTextBody')->andReturn('Body');
    $message->shouldReceive('getHTMLBody')->andReturn('');
    $message->shouldReceive('getHeaders')->andReturn(collect()->put('Date', now()->toRfc2822String()));
    $message->shouldReceive('hasAttachments')->andReturn(false);

    $parser = app(EmailParserService::class);
    $email = $parser->parseAndStoreFromImap($message);

    expect($email->bcc_map_type)->toBe('sender');

    // Should only have ONE email
    expect(\App\Models\Email::where('subject', 'Outgoing Email')->count())->toBe(1);
});

test('external recipient email creates only recipient record', function () {
    // Create IMAP account
    $account = \App\Models\ImapAccount::factory()->create([
        'username' => 'archive@testdomain.com',
        'is_active' => true,
    ]);

    $message = \Mockery::mock(\Webklex\PHPIMAP\Message::class);
    $message->shouldReceive('getMessageId')->andReturn('<recipient-test@example.com>');
    $message->shouldReceive('getRawBody')->andReturn('From: external@example.com...');

    // Mock header object
    $header = \Mockery::mock(\Webklex\PHPIMAP\Header::class);
    $header->raw = "From: external@example.com\r\nTo: recipient@testdomain.com\r\nSubject: Recipient Test\r\n";
    $message->shouldReceive('getHeader')->andReturn($header);
    $message->shouldReceive('getFrom')->andReturn(collect([
        (object) ['mail' => 'external@example.com', 'personal' => null],
    ]));
    $message->shouldReceive('getTo')->andReturn(collect([
        (object) ['mail' => 'recipient@testdomain.com'],
    ]));
    $message->shouldReceive('getCc')->andReturn(null);
    $message->shouldReceive('getDate')->andReturn(now());
    $message->shouldReceive('getInReplyTo')->andReturn(null);
    $message->shouldReceive('getReferences')->andReturn(null);
    $message->shouldReceive('getSubject')->andReturn('Incoming Email');
    $message->shouldReceive('getTextBody')->andReturn('Body');
    $message->shouldReceive('getHTMLBody')->andReturn('');
    $message->shouldReceive('getHeaders')->andReturn(collect()->put('Date', now()->toRfc2822String()));
    $message->shouldReceive('hasAttachments')->andReturn(false);

    $parser = app(EmailParserService::class);
    $email = $parser->parseAndStoreFromImap($message);

    expect($email->bcc_map_type)->toBe('recipient');

    // Should only have ONE email
    expect(\App\Models\Email::where('subject', 'Incoming Email')->count())->toBe(1);
});

test('duplicate email is not stored twice', function () {
    // Create IMAP account
    $account = \App\Models\ImapAccount::factory()->create([
        'username' => 'archive@testdomain.com',
        'is_active' => true,
    ]);

    $message = \Mockery::mock(\Webklex\PHPIMAP\Message::class);
    $message->shouldReceive('getMessageId')->andReturn('<duplicate-test@example.com>');
    $message->shouldReceive('getRawBody')->andReturn('From: sender@example.com...');

    // Mock header object
    $header = \Mockery::mock(\Webklex\PHPIMAP\Header::class);
    $header->raw = "From: sender@example.com\r\nTo: recipient@example.com\r\nSubject: Duplicate Test\r\n";
    $message->shouldReceive('getHeader')->andReturn($header);
    $message->shouldReceive('getFrom')->andReturn(collect([
        (object) ['mail' => 'sender@example.com', 'personal' => null],
    ]));
    $message->shouldReceive('getTo')->andReturn(collect([
        (object) ['mail' => 'recipient@testdomain.com'],
    ]));
    $message->shouldReceive('getCc')->andReturn(null);
    $message->shouldReceive('getDate')->andReturn(now());
    $message->shouldReceive('getInReplyTo')->andReturn(null);
    $message->shouldReceive('getReferences')->andReturn(null);
    $message->shouldReceive('getSubject')->andReturn('Duplicate Test');
    $message->shouldReceive('getTextBody')->andReturn('Body');
    $message->shouldReceive('getHTMLBody')->andReturn('');
    $message->shouldReceive('getHeaders')->andReturn(collect()->put('Date', now()->toRfc2822String()));
    $message->shouldReceive('hasAttachments')->andReturn(false);

    $parser = app(EmailParserService::class);

    // First archiving
    $email1 = $parser->parseAndStoreFromImap($message);
    expect($email1)->not->toBeNull();

    // Second archiving (duplicate)
    $email2 = $parser->parseAndStoreFromImap($message);
    expect($email2)->toBeNull();

    // Should only have ONE email
    expect(\App\Models\Email::where('message_id', '<duplicate-test@example.com>')->count())->toBe(1);
});

// Frontend tests require compiled Vite assets - run `npm run build` first
