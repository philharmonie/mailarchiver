<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$sync = Schedule::command('imap:sync')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->onOneServer();

if ($notifyEmail = config('monitoring.notify_email')) {
    $sync->emailOutputOnFailure($notifyEmail);
}

if ($heartbeatUrl = config('monitoring.heartbeat_url')) {
    $sync->onSuccess(function () use ($heartbeatUrl) {
        Http::timeout(5)->get($heartbeatUrl);
    });
}

if ($heartbeatFailUrl = config('monitoring.heartbeat_url_fail')) {
    $sync->onFailure(function () use ($heartbeatFailUrl) {
        Http::timeout(5)->get($heartbeatFailUrl);
    });
}
