<?php

use App\Models\Email;
use App\Services\EmailParserService;
use Webklex\PHPIMAP\Attribute;

/**
 * webklex hands header values over as Attribute objects rather than strings,
 * and they only turn into text on their way into the database - too late for
 * the column limits to have looked at them. "Solar Inflow" lost 126 mails per
 * sync that way, to error 1406, over and over.
 */
function imapMessageWithSubject(mixed $subject, string $messageId): Webklex\PHPIMAP\Message
{
    $header = Mockery::mock(Webklex\PHPIMAP\Header::class);
    $header->raw = "From: sender@example.com\r\nSubject: whatever";

    $message = Mockery::mock(Webklex\PHPIMAP\Message::class);
    $message->shouldReceive('getMessageId')->andReturn($messageId);
    $message->shouldReceive('getHeader')->andReturn($header);
    $message->shouldReceive('getRawBody')->andReturn('Body');
    $message->shouldReceive('getSubject')->andReturn($subject);
    $message->shouldReceive('getFrom')->andReturn(collect([
        (object) ['mail' => 'sender@example.com', 'personal' => 'Sender'],
    ]));
    $message->shouldReceive('getTo')->andReturn(collect([(object) ['mail' => 'recipient@example.com']]));
    $message->shouldReceive('getCc')->andReturn(null);
    $message->shouldReceive('getDate')->andReturn(null);
    $message->shouldReceive('getInReplyTo')->andReturn(null);
    $message->shouldReceive('getReferences')->andReturn(null);
    $message->shouldReceive('getTextBody')->andReturn('Body');
    $message->shouldReceive('getHTMLBody')->andReturn('');
    $message->shouldReceive('getHeaders')->andReturn(collect());
    $message->shouldReceive('hasAttachments')->andReturn(false);

    return $message;
}

test('a subject that arrives as an object is still cut to fit', function () {
    $message = imapMessageWithSubject(
        new Attribute('subject', str_repeat('a', 600)),
        '<attr@example.com>'
    );

    $email = app(EmailParserService::class)->parseAndStoreFromImap($message);

    expect($email)->not->toBeNull()
        ->and($email->subject)->toBeString()
        ->and(mb_strlen($email->subject))->toBe(EmailParserService::COLUMN_LIMITS['subject'])
        ->and(Email::where('subject', $email->subject)->exists())->toBeTrue();
});

test('a short subject that arrives as an object is stored as text', function () {
    $message = imapMessageWithSubject(new Attribute('subject', 'Short enough'), '<attr2@example.com>');

    $email = app(EmailParserService::class)->parseAndStoreFromImap($message);

    expect($email->subject)->toBe('Short enough');
});
