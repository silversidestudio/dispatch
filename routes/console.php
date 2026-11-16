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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schedule;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

Artisan::command('platform:bootstrap', function () {
    if (User::where('is_platform_admin', true)->exists()) {
        $this->error('A platform administrator already exists.');

        return 1;
    }
    $data = validator([
        'name' => $this->ask('Administrator name'),
        'email' => strtolower(trim((string) $this->ask('Administrator email'))),
        'password' => $this->secret('Password (at least 12 characters)'),
        'password_confirmation' => $this->secret('Confirm password'),
    ], [
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255|unique:users,email',
        'password' => 'required|string|min:12|confirmed',
    ])->validate();
    $u = new User;
    $u->forceFill(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'email_verified_at' => now(), 'is_platform_admin' => true])->save();
    AuditLog::create(['user_id' => $u->id, 'action' => 'platform.admin_bootstrapped']);
    $this->info('Initial platform administrator created. Sign in to provision the company.');
})->purpose('Create the first platform administrator interactively');
Artisan::command('platform:promote {email}', function () {
    $u = User::where('email', $this->argument('email'))->first();
    if (! $u) {
        $this->error('No account exists for that email. Use an invitation or bootstrap the first administrator.');

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

Artisan::command('dispatch:launch-check', function () {
    $checks = [
        'Production environment' => app()->isProduction(),
        'HTTPS application URL' => str_starts_with((string) config('app.url'), 'https://'),
        'Debug disabled' => ! config('app.debug'),
        'Application key configured' => filled(config('app.key')),
        'Secure encrypted sessions' => config('session.secure') && config('session.encrypt'),
        'Redis queue selected' => config('queue.default') === 'redis',
        'Queue retry window exceeds worker timeout' => config('queue.connections.redis.retry_after') > config('horizon.defaults.supervisor-1.timeout'),
    ];
    try {
        DB::select('select 1');
        $checks['Database reachable'] = true;
    } catch (Throwable) {
        $checks['Database reachable'] = false;
    }
    try {
        Redis::connection(config('queue.connections.redis.connection'))->ping();
        $checks['Redis reachable'] = true;
        $masters = collect(app(MasterSupervisorRepository::class)->all());
        $checks['Horizon running'] = $masters->isNotEmpty() && $masters->every(fn ($master) => $master->status === 'running');
    } catch (Throwable) {
        $checks['Redis or Horizon status unavailable'] = false;
    }
    $this->table(['Check', 'Result'], collect($checks)->map(fn ($ok, $name) => [$name, $ok ? 'PASS' : 'CHECK'])->values()->all());
    $this->line('Live sending: '.(config('dispatch.sendgrid.live_enabled') ? 'enabled' : 'disabled'));
    $this->line('Restricted pilot: '.(config('dispatch.pilot.enabled') ? 'enabled' : 'disabled'));
    $this->line('Account mail: '.(config('dispatch.sendgrid.auth_enabled') ? 'SendGrid configured to handle account notifications; verify separately' : 'local log only'));
    $this->comment('Check the scheduler, external HTTPS/webhooks, backups, monitoring and real mail separately.');

    return in_array(false, $checks, true) ? 1 : 0;
})->purpose('Check local deployment prerequisites without sending email or printing credentials');
