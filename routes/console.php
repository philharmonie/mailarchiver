<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Email archiving scheduler
// Runs every 15 minutes to check if any IMAP accounts need syncing based on their configured intervals
Schedule::command('imap:sync')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->onOneServer();
