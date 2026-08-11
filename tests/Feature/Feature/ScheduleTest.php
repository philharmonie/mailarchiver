<?php

use Illuminate\Console\Scheduling\Schedule;

function syncEvent(): \Illuminate\Console\Scheduling\Event
{
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'imap:sync'));

    expect($events)->toHaveCount(1);

    return $events->first();
}

test('the sync mutex expires long before a day', function () {
    // Laravel's default is 1440 minutes. A run killed between taking the mutex
    // and reaching schedule:finish would stop the archive for that long, and
    // nothing would say so - a sync that never starts logs nothing.
    expect(syncEvent()->expiresAt)->toBeLessThanOrEqual(30);
});

test('the sync mutex outlasts a normal run by a wide margin', function () {
    // The other direction: if the lock lapses under a run that is merely slow,
    // a second sync joins the first and archives the same mail twice.
    expect(syncEvent()->expiresAt)->toBeGreaterThanOrEqual(15);
});
