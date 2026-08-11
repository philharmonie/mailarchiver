<?php

use App\Services\EmailParserService;

test('a body that is not valid utf-8 is archived anyway', function () {
    // Two ways a mail arrives unstorable: a latin-1 byte in a body labelled
    // utf-8, and an emoji written as the surrogate pair utf-8 has no room for.
    $body = "Geb\xA7hr 100 EUR \xED\xA0\xBD\xED\xB9\x82 danke";

    $raw = implode("\n", [
        'Message-ID: <encoding@example.com>',
        'From: Sender <sender@example.com>',
        'To: recipient@example.com',
        'Subject: Rechnung',
        'Date: Mon, 10 Aug 2026 12:00:00 +0200',
        '',
        $body,
    ]);

    $email = app(EmailParserService::class)->parseAndStore($raw);

    expect(mb_check_encoding($email->body_text, 'UTF-8'))->toBeTrue();
    expect($email->body_text)->toContain('hr 100 EUR');
    expect($email->body_text)->toContain('danke');
});

test('text that is already utf-8 is left alone', function () {
    expect(EmailParserService::toUtf8('Grüße 😀'))->toBe('Grüße 😀');
    expect(EmailParserService::toUtf8(['a' => 'Grüße', 'b' => ['c' => 'ok']]))
        ->toBe(['a' => 'Grüße', 'b' => ['c' => 'ok']]);
    expect(EmailParserService::toUtf8(null))->toBeNull();
    expect(EmailParserService::toUtf8(42))->toBe(42);
});

test('an attachment named in broken bytes gets a storable name', function () {
    expect(mb_check_encoding(EmailParserService::toUtf8("Rechnung_M\xE4rz.pdf"), 'UTF-8'))->toBeTrue();
});
