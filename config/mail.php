<?php

// Phase 1A deliberately has no external mail transport, regardless of MAIL_MAILER.
return ['default' => 'log', 'mailers' => ['log' => ['transport' => 'log', 'channel' => env('MAIL_LOG_CHANNEL')], 'array' => ['transport' => 'array']], 'from' => ['address' => 'development@example.test', 'name' => 'Dispatch local development']];
