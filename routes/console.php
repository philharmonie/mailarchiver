<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tick more often than the shortest per-account sync_interval so the
// "due since last sync" check has fine-grained chances to fire. The
// imap:sync command itself short-circuits when nothing is due.
// The mutex is what a crashed run leaves behind. Without a length it lasts
// 1440 minutes, so one run killed between taking it and reaching
// schedule:finish stops the archive syncing for a day - and silently, because
// a sync that never starts writes no error for a check to find. That is how
// hosting-4 sat four hours behind while it reported healthy.
//
// 30 minutes bounds the damage without inviting the opposite one: a run takes
// about three minutes, so the lock only lapses under a run that is already far
// outside anything normal, and a second sync alongside the first would archive
// the same mail twice.
$sync = Schedule::command('imap:sync')
    ->everyFiveMinutes()
    ->withoutOverlapping(30)
    ->runInBackground()
    ->onOneServer();

if ($notifyEmail = config('monitoring.notify_email')) {
    $sync->emailOutputOnFailure($notifyEmail);
}

// The heartbeat hung off this command's exit code and said nothing about the
// archive: a run where nothing was due succeeds, so the ping stayed green
// through hours of standstill. monitoring:heartbeat reads last_sync_at
// instead and is the only voice towards the check - two writers would flap,
// one pushing "down" for a failed run while the other still finds the
// accounts fresh.
Schedule::command('monitoring:heartbeat')
    ->everyFiveMinutes()
    ->onOneServer();
