<?php

return [
    'provider' => env('DISPATCH_DELIVERY_PROVIDER', 'simulation'),
    'max_recipients' => 10000, 'retry_seconds' => 30,
    'sendgrid' => [
        'auth_enabled' => (bool) env('SENDGRID_AUTH_ENABLED', false), 'auth_from' => env('SENDGRID_AUTH_FROM'),
        'enabled' => (bool) env('SENDGRID_ENABLED', false), 'live_enabled' => (bool) env('SENDGRID_LIVE_SENDING_ENABLED', false),
        'api_key' => env('SENDGRID_API_KEY'), 'webhook_key' => env('SENDGRID_WEBHOOK_VERIFICATION_KEY'),
        'per_minute' => (int) env('SENDGRID_REQUESTS_PER_MINUTE', 60), 'timeout' => 15, 'max_attempts' => 5,
        'signature_tolerance' => 300, 'identity_max_age_days' => 7,
    ],
    'retention' => ['webhook_days' => (int) env('DISPATCH_WEBHOOK_RETENTION_DAYS', 14), 'engagement_days' => (int) env('DISPATCH_ENGAGEMENT_RETENTION_DAYS', 90)],
];
