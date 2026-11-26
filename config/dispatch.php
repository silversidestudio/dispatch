<?php

return [
    'provider' => env('DISPATCH_DELIVERY_PROVIDER', 'simulation'),
    'max_recipients' => 10000, 'retry_seconds' => 30,
    'import_max_rows' => (int) env('DISPATCH_IMPORT_MAX_ROWS', env('DISPATCH_CONTACT_IMPORT_MAX_ROWS', 25000)),
    'contact_import' => ['chunk_size' => 250],
    'suppression_import' => ['chunk_size' => 250],
    'sendgrid' => [
        'enabled' => (bool) env('SENDGRID_ENABLED', false), 'live_enabled' => (bool) env('SENDGRID_LIVE_SENDING_ENABLED', false),
        'per_minute' => (int) env('SENDGRID_REQUESTS_PER_MINUTE', 60), 'timeout' => 15, 'max_attempts' => 5,
        'signature_tolerance' => 300, 'identity_max_age_days' => 7,
    ],
    'pilot' => [
        'enabled' => (bool) env('DISPATCH_PILOT_ENABLED', true),
        'daily_limit' => (int) env('DISPATCH_PILOT_DAILY_LIMIT', 25),
        'recipient_allowlist_enabled' => (bool) env('DISPATCH_PILOT_RECIPIENT_ALLOWLIST_ENABLED', true),
        'recipients' => array_values(array_filter(array_map('trim', explode(',', env('DISPATCH_PILOT_RECIPIENTS', ''))))),
        'organisation_ids' => array_values(array_filter(array_map('trim', explode(',', env('DISPATCH_PILOT_ORGANISATION_IDS', ''))))),
    ],
    'retention' => ['webhook_days' => (int) env('DISPATCH_WEBHOOK_RETENTION_DAYS', 14), 'engagement_days' => (int) env('DISPATCH_ENGAGEMENT_RETENTION_DAYS', 90)],
];
