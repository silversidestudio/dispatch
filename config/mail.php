<?php

$mailer = env('MAIL_MAILER', 'log');

return [
    'default' => in_array($mailer, ['log', 'smtp', 'array'], true) ? $mailer : 'log',
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME', 'smtp'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => (int) env('MAIL_PORT', 587),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'require_tls' => true,
            'timeout' => 10,
        ],
        'log' => ['transport' => 'log', 'channel' => env('MAIL_LOG_CHANNEL') ?: 'account_mail'],
        'array' => ['transport' => 'array'],
    ],
    'from' => ['address' => env('MAIL_FROM_ADDRESS', 'development@example.test'), 'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Dispatch'))],
];
