<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notification Email
    |--------------------------------------------------------------------------
    |
    | Recipient address for scheduler failure output. When the IMAP sync
    | command exits with a non-zero status, Laravel mails its captured
    | stdout/stderr to this address. Leave null to disable email alerts.
    |
    */

    'notify_email' => env('MONITORING_NOTIFY_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Heartbeat URLs
    |--------------------------------------------------------------------------
    |
    | Optional push-based health-check endpoints. The success URL is hit
    | after every successful scheduler run (dead-man's switch — if the
    | check stops receiving pings it alerts). The failure URL is hit when
    | the scheduler reports a failure. Compatible with any provider that
    | exposes a simple GET endpoint (Uptime Kuma push monitor,
    | healthchecks.io, BetterUptime heartbeat, custom webhooks, etc.).
    |
    */

    'heartbeat_url' => env('MONITORING_HEARTBEAT_URL'),

    'heartbeat_url_fail' => env('MONITORING_HEARTBEAT_URL_FAIL'),

    /*
    |--------------------------------------------------------------------------
    | Stale Threshold
    |--------------------------------------------------------------------------
    |
    | Minutes after which an IMAP account's last_sync_at is considered
    | stale on the admin dashboard. Accounts above this threshold are
    | rendered with a warning indicator.
    |
    */

    'stale_threshold_minutes' => (int) env('MONITORING_STALE_THRESHOLD_MINUTES', 60),

];
