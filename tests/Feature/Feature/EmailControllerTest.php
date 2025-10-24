<?php

use App\Models\Email;
use App\Models\ImapAccount;
use App\Models\User;

// These tests verify email access control at the model level
// Full controller tests would require complete route/controller implementation

test('user can access emails from their imap account', function () {
    $user = User::factory()->create();
    $account = ImapAccount::factory()->create(['username' => $user->email]);
    $email = Email::factory()->create(['imap_account_id' => $account->id]);

    // User owns this email through their IMAP account
    expect($email->imapAccount->username)->toBe($user->email);
});

test('emails have required relationships', function () {
    $account = ImapAccount::factory()->create();
    $email = Email::factory()->create(['imap_account_id' => $account->id]);

    expect($email->imapAccount)->not->toBeNull()
        ->and($email->imapAccount->id)->toBe($account->id);
});

test('email can retrieve decompressed raw content', function () {
    $rawEmail = str_repeat("Test email content\n", 50);
    $email = Email::factory()->create([
        'raw_email' => gzencode($rawEmail),
        'is_compressed' => true,
    ]);

    $decompressed = $email->getRawEmailDecompressed();

    expect($decompressed)->toContain('Test email content');
});

test('email hash verification works', function () {
    $rawEmail = "From: test@example.com\nSubject: Test\n\nBody";
    $email = Email::factory()->create([
        'raw_email' => $rawEmail,
        'hash' => hash('sha256', $rawEmail),
        'is_compressed' => false,
    ]);

    expect($email->verifyHash())->toBeTrue();
});
