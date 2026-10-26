<?php

use App\Delivery\SubmissionResult;
use App\Jobs\ProcessSendGridWebhook;
use App\Jobs\PumpCampaigns;
use App\Models\AuditLog;
use App\Models\DeliveryAttempt;
use App\Models\DeliveryEvent;
use App\Models\User;
use App\Models\WebhookBatch;
use App\Services\SendGridSubmissionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

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

Artisan::command('dispatch:tick', function () {
    app(PumpCampaigns::class)->handle();
    $this->info('Due simulation work dispatched.');
})->purpose('Recover due scheduled campaigns and deferred simulation recipients');
Schedule::command('dispatch:tick')->everyMinute()->withoutOverlapping();

Artisan::command('dispatch:recover-submissions', function () {
    DeliveryAttempt::where('provider', 'sendgrid')->where('outcome', 'submitting')->where('started_at', '<', now()->subMinutes(5))->orderBy('id')->chunkById(100, function ($attempts) {
        foreach ($attempts as $a) {
            app(SendGridSubmissionService::class)->finish($a, new SubmissionResult('uncertain', '', null, 'worker_acknowledgement_missing'));
        }
    });
    WebhookBatch::whereIn('status', ['queued', 'failed'])->where('created_at', '<', now()->subMinute())->limit(100)->get()->each(fn ($b) => ProcessSendGridWebhook::dispatch($b->id));
    $this->info('Uncertain submissions held; pending webhook batches requeued.');
});
Schedule::command('dispatch:recover-submissions')->everyMinute()->withoutOverlapping();
Artisan::command('dispatch:prune-feedback', function () {
    $b = WebhookBatch::where('status', 'completed')->where('created_at', '<', now()->subDays(max(1, config('dispatch.retention.webhook_days'))))->delete();
    $e = DeliveryEvent::whereIn('type', ['open', 'click'])->where('created_at', '<', now()->subDays(max(1, config('dispatch.retention.engagement_days'))))->delete();
    $this->info("Pruned $b completed webhook envelopes and $e expired engagement events. Historical engagement metrics may decrease after retention expiry.");
});
Schedule::command('dispatch:prune-feedback')->daily();
