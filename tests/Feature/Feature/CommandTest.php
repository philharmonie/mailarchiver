<?php

use App\Models\ImapAccount;

test('archive emails command validates account exists', function () {
    $this->artisan('emails:archive', ['--account' => 999])
        ->expectsOutput('Account with ID 999 not found.')
        ->assertFailed();
});

test('archive emails command skips inactive accounts', function () {
    $account = ImapAccount::factory()->create([
        'is_active' => false,
        'name' => 'Inactive Test Account',
    ]);

    $this->artisan('emails:archive', ['--account' => $account->id])
        ->assertSuccessful();
});

test('imap sync command can run without errors', function () {
    $this->artisan('imap:sync')
        ->assertSuccessful();
});
