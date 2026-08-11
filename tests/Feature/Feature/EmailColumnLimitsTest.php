<?php

use App\Models\Email;
use App\Services\EmailParserService;

/**
 * Build a raw mail with the headers a test wants to overrun.
 */
function rawMailWith(array $headers): string
{
    $headers = array_merge([
        'Message-ID' => '<limits@example.com>',
        'From' => 'Sender <sender@example.com>',
        'To' => 'recipient@example.com',
        'Subject' => 'Rechnung',
        'Date' => 'Mon, 10 Aug 2026 12:00:00 +0200',
    ], $headers);

    $lines = [];
    foreach ($headers as $name => $value) {
        $lines[] = "$name: $value";
    }

    return implode("\n", [...$lines, '', 'Body']);
}

test('a subject longer than its column is archived, cut to what fits', function () {
    $subject = str_repeat('a', 600);

    $email = app(EmailParserService::class)->parseAndStore(rawMailWith(['Subject' => $subject]));

    expect(mb_strlen($email->subject))->toBe(512);
    expect($email->subject)->toBe(mb_substr($subject, 0, 512));
});

test('a subject that would not have fit before is now stored whole', function () {
    // The length that used to fail: past the old 255, inside the new 512.
    $subject = str_repeat('b', 400);

    $email = app(EmailParserService::class)->parseAndStore(rawMailWith(['Subject' => $subject]));

    expect($email->subject)->toBe($subject);
});

test('the mail is kept whole even where the header is cut', function () {
    $subject = str_repeat('c', 600);

    $email = app(EmailParserService::class)->parseAndStore(rawMailWith(['Subject' => $subject]));

    // raw_email is the copy GoBD asks to stay faithful, so the subject has to
    // be in it at its full length even though the column next to it is cut.
    expect($email->raw_email)->toContain($subject);
});

test('a sender name longer than its column does not lose the mail', function () {
    $name = str_repeat('d', 400);

    $email = app(EmailParserService::class)->parseAndStore(
        rawMailWith(['From' => "\"$name\" <sender@example.com>"])
    );

    expect(mb_strlen($email->from_name))->toBeLessThanOrEqual(255);
    expect($email->from_address)->toBe('sender@example.com');
});

test('cutting counts characters, not bytes', function () {
    // Umlauts are two bytes each in utf-8: cutting bytes would land in the
    // middle of one and hand MySQL a broken character.
    $subject = str_repeat('ä', 600);

    $email = app(EmailParserService::class)->parseAndStore(rawMailWith(['Subject' => $subject]));

    expect(mb_strlen($email->subject))->toBe(512);
    expect(mb_check_encoding($email->subject, 'UTF-8'))->toBeTrue();
});

test('a header that fits is left exactly as it came', function () {
    $email = app(EmailParserService::class)->parseAndStore(rawMailWith(['Subject' => 'Grüße 😀']));

    expect($email->subject)->toBe('Grüße 😀');
    expect(Email::count())->toBe(1);
});
