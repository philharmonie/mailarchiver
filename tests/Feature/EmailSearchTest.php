<?php

use App\Models\Email;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    config()->set('scout.driver', 'collection');
});

it('finds the user own emails by subject', function () {
    $user = User::factory()->create(['email' => 'owner@example.com']);

    $match = Email::factory()->create([
        'bcc_map_type' => 'recipient',
        'to_addresses' => [$user->email],
        'subject' => 'Quarterly invoice attached',
    ]);

    Email::factory()->create([
        'bcc_map_type' => 'recipient',
        'to_addresses' => [$user->email],
        'subject' => 'Unrelated newsletter',
    ]);

    $response = actingAs($user)->get(route('emails.index', ['search' => 'invoice']));

    $response->assertOk();
    $ids = collect($response->viewData('page')['props']['emails']['data'])->pluck('id');
    expect($ids)->toContain($match->id)->toHaveCount(1);
});

it('does not return emails the user does not own', function () {
    $user = User::factory()->create(['email' => 'owner@example.com']);

    Email::factory()->create([
        'bcc_map_type' => 'recipient',
        'to_addresses' => ['someone-else@example.com'],
        'subject' => 'Secret invoice',
    ]);

    $response = actingAs($user)->get(route('emails.index', ['search' => 'invoice']));

    $response->assertOk();
    expect($response->viewData('page')['props']['emails']['data'])->toBeEmpty();
});

it('matches on sender address too', function () {
    $user = User::factory()->create(['email' => 'owner@example.com']);

    $match = Email::factory()->create([
        'bcc_map_type' => 'sender',
        'from_address' => $user->email,
        'subject' => 'no keyword here',
    ]);

    $response = actingAs($user)->get(route('emails.index', ['search' => $user->email]));

    $response->assertOk();
    $ids = collect($response->viewData('page')['props']['emails']['data'])->pluck('id');
    expect($ids)->toContain($match->id);
});
