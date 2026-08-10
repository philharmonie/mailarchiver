<?php

use App\Models\Setting;
use App\Services\EmailParserService;
use Illuminate\Support\Facades\Log;

test('a failed webhook logs the driver error, not the mail the statement carried', function () {
    Setting::set('mailcow_enabled', true);

    // What a database error looks like here: the statement it failed on is the
    // mail, so the message runs for as long as the mail does.
    $body = str_repeat('secret mail body. ', 500);

    $this->mock(EmailParserService::class)
        ->shouldReceive('parseAndStore')
        ->andThrow(new \Illuminate\Database\QueryException(
            'mysql',
            "insert into `emails` (`body_text`) values ('{$body}')",
            [],
            new \PDOException("SQLSTATE[HY000]: General error: 1366 Incorrect string value for column 'body_text'")
        ));

    Log::spy();

    $this->postJson('/api/webhook/mailcow', [], ['Content-Type' => 'text/plain'])
        ->assertStatus(500);

    Log::shouldHaveReceived('error')
        ->withArgs(function (string $message, array $context) {
            expect($message)->toBe('Mailcow webhook failed');
            expect($context['error'])->toContain('Incorrect string value');
            expect($context['error'])->not->toContain('secret mail body');
            expect(strlen($context['error']))->toBeLessThanOrEqual(EmailParserService::ERROR_EXCERPT);

            return true;
        });
});
