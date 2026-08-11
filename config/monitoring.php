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

    'notify_email' => env('MONITORING_NOTIFY_EMAIL') ?: null,

    /*
    |--------------------------------------------------------------------------
    | Heartbeat URLs
    |--------------------------------------------------------------------------
    |
    | Optional push-based health-check endpoints, pinged by the scheduled
    | `monitoring:heartbeat` command. The success URL is hit while every
    | active account is synced within the stale threshold below (dead-man's
    | switch — if the check stops receiving pings it alerts); the failure URL
    | is hit as soon as one of them falls behind, so the monitor goes red at
    | that moment instead of waiting out its own interval. Compatible with any
    | provider that exposes a simple GET endpoint (Uptime Kuma push monitor,
    | healthchecks.io, BetterUptime heartbeat, custom webhooks, etc.) — for
    | Uptime Kuma, point the failure URL at the same token with
    | ?status=down&msg=stale.
    |
    */

    'heartbeat_url' => env('MONITORING_HEARTBEAT_URL') ?: null,

    'heartbeat_url_fail' => env('MONITORING_HEARTBEAT_URL_FAIL') ?: null,

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

    /*
    |--------------------------------------------------------------------------
    | Sync Memory Limit
    |--------------------------------------------------------------------------
    |
    | PHP memory_limit applied at the start of `imap:sync`. Mailbox parsing
    | streams full message bodies, so 512M is often too tight for archiving
    | thousands of messages. Format follows php.ini conventions ("1024M",
    | "2G", "-1" for unlimited).
    |
    */

    'sync_memory_limit' => env('MONITORING_SYNC_MEMORY_LIMIT', '1024M'),

];
