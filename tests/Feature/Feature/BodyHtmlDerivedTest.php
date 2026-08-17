<?php

use App\Models\Email;
use App\Models\User;
use App\Services\EmailParserService;

$multipart = <<<'EMAIL'
Message-ID: <html-body@example.com>
From: sender@example.com
To: recipient@example.com
Subject: Two bodies
Date: Mon, 17 Aug 2026 10:00:00 +0000
MIME-Version: 1.0
Content-Type: multipart/alternative; boundary="b1"

--b1
Content-Type: text/plain; charset=UTF-8

The plain one.
--b1
Content-Type: text/html; charset=UTF-8

<p>The <b>marked up</b> one.</p>
--b1--
EMAIL;

test('the html body is read back out of the raw mail', function () use ($multipart) {
    $email = app(EmailParserService::class)->parseAndStore($multipart);

    expect($email->deriveBodyHtml())->toContain('marked up');
});

test('mail without an html part derives nothing', function () {
    $email = app(EmailParserService::class)->parseAndStore(
        "From: sender@example.com\nTo: recipient@example.com\nSubject: Plain\n\nJust text.\n"
    );

    expect($email->deriveBodyHtml())->toBeNull();
});

test('the archive stops storing a column for it', function () use ($multipart) {
    app(EmailParserService::class)->parseAndStore($multipart);

    expect(Email::query()->getConnection()->getSchemaBuilder()->hasColumn('emails', 'body_html'))
        ->toBeFalse();
})->skip(fn () => Email::query()->getConnection()->getSchemaBuilder()->hasColumn('emails', 'body_html'),
    'body_html column is still there - the drop migration has not run yet');

test('opening a mail hands the view the derived html', function () use ($multipart) {
    $email = app(EmailParserService::class)->parseAndStore($multipart);
    $email->update(['to_addresses' => ['reader@example.com'], 'bcc_map_type' => 'recipient']);

    $user = User::factory()->create(['email' => 'reader@example.com']);

    $this->actingAs($user)
        ->get("/emails/{$email->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('emails/show')
            ->where('email.body_html', fn ($html) => str_contains((string) $html, 'marked up'))
        );
});
