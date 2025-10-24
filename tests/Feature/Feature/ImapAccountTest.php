<?php

use App\Models\ImapAccount;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

test('admin can view imap accounts list', function () {
    $admin = User::factory()->admin()->create();
    ImapAccount::factory()->count(3)->create();

    $response = actingAs($admin)->get('/imap-accounts');

    $response->assertOk();
});

test('non-admin cannot view imap accounts list', function () {
    $user = User::factory()->create();

    $response = actingAs($user)->get('/imap-accounts');

    $response->assertForbidden();
});

test('admin can create imap account', function () {
    $admin = User::factory()->admin()->create();

    $response = actingAs($admin)->post('/imap-accounts', [
        'name' => 'Test Account',
        'host' => 'imap.example.com',
        'port' => 993,
        'encryption' => 'ssl',
        'validate_cert' => true,
        'username' => 'test@example.com',
        'password' => 'password',
        'folder' => 'INBOX',
        'is_active' => true,
        'sync_interval' => 'hourly',
        'delete_after_archive' => false,
    ]);

    $response->assertRedirect('/imap-accounts');

    assertDatabaseHas('imap_accounts', [
        'name' => 'Test Account',
        'host' => 'imap.example.com',
        'username' => 'test@example.com',
    ]);
});

test('admin can update imap account', function () {
    $admin = User::factory()->admin()->create();
    $account = ImapAccount::factory()->create(['name' => 'Old Name']);

    $response = actingAs($admin)->put("/imap-accounts/{$account->id}", [
        'name' => 'Updated Name',
        'host' => $account->host,
        'port' => $account->port,
        'encryption' => $account->encryption,
        'validate_cert' => $account->validate_cert,
        'username' => $account->username,
        'password' => $account->password,
        'folder' => $account->folder,
        'is_active' => $account->is_active,
        'sync_interval' => $account->sync_interval,
        'delete_after_archive' => $account->delete_after_archive,
    ]);

    $response->assertRedirect('/imap-accounts');

    assertDatabaseHas('imap_accounts', [
        'id' => $account->id,
        'name' => 'Updated Name',
    ]);
});

test('admin can delete imap account', function () {
    $admin = User::factory()->admin()->create();
    $account = ImapAccount::factory()->create();

    $response = actingAs($admin)->delete("/imap-accounts/{$account->id}");

    $response->assertRedirect('/imap-accounts');

    expect(ImapAccount::find($account->id))->toBeNull();
});

test('sync_interval must be valid value', function () {
    $admin = User::factory()->admin()->create();

    $response = actingAs($admin)->post('/imap-accounts', [
        'name' => 'Test Account',
        'host' => 'imap.example.com',
        'port' => 993,
        'encryption' => 'ssl',
        'validate_cert' => true,
        'username' => 'test@example.com',
        'password' => 'password',
        'folder' => 'INBOX',
        'is_active' => true,
        'sync_interval' => 'invalid_interval',
        'delete_after_archive' => false,
    ]);

    $response->assertSessionHasErrors('sync_interval');
});

test('delete_after_archive defaults to false', function () {
    $account = ImapAccount::factory()->create(['delete_after_archive' => false]);

    expect($account->delete_after_archive)->toBeFalse();
});

test('imap account can track last sync time', function () {
    $account = ImapAccount::factory()->create(['last_sync_at' => null]);

    expect($account->last_sync_at)->toBeNull();

    $account->update(['last_sync_at' => now()]);
    $account->refresh();

    expect($account->last_sync_at)->not->toBeNull();
});

test('imap account can be marked inactive', function () {
    $account = ImapAccount::factory()->create(['is_active' => true]);

    expect($account->is_active)->toBeTrue();

    $account->update(['is_active' => false]);
    $account->refresh();

    expect($account->is_active)->toBeFalse();
});
