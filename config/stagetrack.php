<?php

return [
    'version' => '1.0.0',

    // Treat the application as installed even without storage/app/installed.lock (used by tests/CI).
    'assume_installed' => (bool) env('STAGETRACK_ASSUME_INSTALLED', false),

    'backups' => [
        'disk_path' => storage_path('app/backups'),
        'keep' => (int) env('STAGETRACK_BACKUPS_KEEP', 14),
    ],

    'updates' => [
        // Optional JSON feed: {"version":"1.1.0","notes":"...","download_url":"..."}
        'feed_url' => env('STAGETRACK_UPDATE_FEED'),
        'staging_path' => storage_path('app/updates'),
    ],

    // Shared secret for hosts that can only trigger the scheduler via an HTTP cron (cPanel "wget" cron).
    'web_cron_token' => env('STAGETRACK_CRON_TOKEN'),

    'search' => [
        'limit' => 8,
    ],
];
