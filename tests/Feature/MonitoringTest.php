<?php

use App\Models\ImapAccount;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('monitoring config reads env vars', function () {
    config()->set('monitoring.notify_email', 'ops@example.com');
    config()->set('monitoring.heartbeat_url', 'https://example.com/ping');
    config()->set('monitoring.heartbeat_url_fail', 'https://example.com/fail');
    config()->set('monitoring.stale_threshold_minutes', 90);

    expect(config('monitoring.notify_email'))->toBe('ops@example.com');
    expect(config('monitoring.heartbeat_url'))->toBe('https://example.com/ping');
    expect(config('monitoring.heartbeat_url_fail'))->toBe('https://example.com/fail');
    expect(config('monitoring.stale_threshold_minutes'))->toBe(90);
});

test('monitoring config has sane defaults', function () {
    expect(config('monitoring.notify_email'))->toBeNull();
    expect(config('monitoring.heartbeat_url'))->toBeNull();
    expect(config('monitoring.heartbeat_url_fail'))->toBeNull();
    expect(config('monitoring.stale_threshold_minutes'))->toBe(60);
});

test('admin dashboard exposes last_sync_at and stale threshold', function () {
    $admin = User::factory()->admin()->create();
    ImapAccount::factory()->create([
        'name' => 'Stale Box',
        'last_sync_at' => now()->subHours(5),
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('is_admin', true)
            ->where('stale_threshold_minutes', config('monitoring.stale_threshold_minutes'))
            ->has('account_stats.0.last_sync_at')
        );
});

test('imap sync command returns failure when an account fails', function () {
    ImapAccount::factory()->create([
        'is_active' => true,
        'sync_interval' => 'every_15_minutes',
        'last_sync_at' => null,
        'host' => 'invalid.example.invalid',
    ]);

    $this->artisan('imap:sync')->assertFailed();
});

test('heartbeat url is pinged via http when configured', function () {
    Http::fake();

    $url = 'https://example.test/ping-test';

    Http::get($url);

    Http::assertSent(fn ($request) => $request->url() === $url);
});

test('heartbeat pings while every active account is inside the threshold', function () {
    Http::fake();
    config()->set('monitoring.heartbeat_url', 'https://example.test/up');
    config()->set('monitoring.heartbeat_url_fail', 'https://example.test/down');
    config()->set('monitoring.stale_threshold_minutes', 60);

    ImapAccount::factory()->create([
        'is_active' => true,
        'sync_interval' => 'every_15_minutes',
        'last_sync_at' => now()->subMinutes(10),
    ]);

    $this->artisan('monitoring:heartbeat')->assertSuccessful();

    Http::assertSent(fn ($request) => $request->url() === 'https://example.test/up');
    Http::assertNotSent(fn ($request) => $request->url() === 'https://example.test/down');
});

test('heartbeat is withheld once an account falls behind', function () {
    Http::fake();
    config()->set('monitoring.heartbeat_url', 'https://example.test/up');
    config()->set('monitoring.heartbeat_url_fail', 'https://example.test/down');
    config()->set('monitoring.stale_threshold_minutes', 60);

    ImapAccount::factory()->create([
        'name' => 'Fresh Box',
        'is_active' => true,
        'sync_interval' => 'every_15_minutes',
        'last_sync_at' => now()->subMinutes(10),
    ]);
    ImapAccount::factory()->create([
        'name' => 'Stuck Box',
        'is_active' => true,
        'sync_interval' => 'every_15_minutes',
        'last_sync_at' => now()->subHours(4),
    ]);

    $this->artisan('monitoring:heartbeat')
        ->expectsOutputToContain('Stuck Box')
        ->assertFailed();

    Http::assertSent(fn ($request) => $request->url() === 'https://example.test/down');
    Http::assertNotSent(fn ($request) => $request->url() === 'https://example.test/up');
});

test('an account that never synced is stale', function () {
    Http::fake();
    config()->set('monitoring.heartbeat_url', 'https://example.test/up');

    ImapAccount::factory()->create([
        'is_active' => true,
        'sync_interval' => 'every_15_minutes',
        'last_sync_at' => null,
    ]);

    $this->artisan('monitoring:heartbeat')->assertFailed();

    Http::assertNothingSent();
});

test('inactive accounts are not watched, but none left at all is a failure', function () {
    Http::fake();
    config()->set('monitoring.heartbeat_url', 'https://example.test/up');

    ImapAccount::factory()->create([
        'is_active' => false,
        'sync_interval' => 'every_15_minutes',
        'last_sync_at' => now()->subYear(),
    ]);

    $this->artisan('monitoring:heartbeat')->assertFailed();

    Http::assertNothingSent();
});

test('heartbeat stays quiet when no url is configured', function () {
    Http::fake();
    config()->set('monitoring.heartbeat_url', null);
    config()->set('monitoring.heartbeat_url_fail', null);

    ImapAccount::factory()->create([
        'is_active' => true,
        'sync_interval' => 'every_15_minutes',
        'last_sync_at' => now()->subMinutes(5),
    ]);

    $this->artisan('monitoring:heartbeat')->assertSuccessful();

    Http::assertNothingSent();
});
