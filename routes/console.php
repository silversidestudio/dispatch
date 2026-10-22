<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

Artisan::command('platform:promote {email}', function () {
    $u = User::where('email', $this->argument('email'))->first();
    if (! $u) {
        $this->error('Register this account locally first.');

        return 1;
    }
    $u->is_platform_admin = true;
    $u->save();
    AuditLog::create(['user_id' => $u->id, 'action' => 'platform.admin_promoted']);
    $this->info('Platform administrator enabled. Email verification remains required.');
})->purpose('Promote an existing local user without exposing a password');
Artisan::command('development:verify {email}', function () {
    if (! app()->environment('local')) {
        $this->error('Local environment only.');

        return 1;
    }
    $u = User::where('email', $this->argument('email'))->firstOrFail();
    $u->markEmailAsVerified();
    AuditLog::create(['user_id' => $u->id, 'action' => 'development.email_verified']);
    $this->info('Local account verified.');
})->purpose('Verify a local development account without sending email');
