<?php

use App\Models\Organisation;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

it('uses log delivery by default and builds a TLS-required SMTP transport when selected', function () {
    expect(config('mail.default'))->toBe('array'); // phpunit.xml prevents external delivery.
    expect(config('mail.mailers.log.channel'))->toBe('account_mail');
    expect(config('logging.channels.account_mail.permission'))->toBe(0600);
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'mail.example.test', 'mail.mailers.smtp.port' => 587]);
    $transport = Mail::mailer()->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(EsmtpTransport::class);
    expect($transport->isTlsRequired())->toBeTrue();
    expect($transport->getStream()->getHost())->toBe('mail.example.test');
});

it('renders all three system messages through the selected mailer independently of SendGrid live and pilot gates', function () {
    Http::preventStrayRequests();
    Http::fake();
    config(['mail.default' => 'array', 'app.name' => 'Campaign Manager', 'mail.from.name' => 'Campaign Manager', 'mail.from.address' => 'accounts@example.test', 'dispatch.sendgrid.live_enabled' => false]);
    $user = User::factory()->unverified()->create();
    $company = Organisation::create(['name' => 'Client Company']);
    $user->sendEmailVerificationNotification();
    $user->sendPasswordResetNotification('local-test-token');
    $url = app(MembershipService::class)->invite($company, 'new@example.test', 'viewer');

    $messages = Mail::mailer()->getSymfonyTransport()->messages()->map(fn ($m) => $m->getOriginalMessage());
    expect($messages)->toHaveCount(3);
    expect($messages[0]->getTo()[0]->getAddress())->toBe($user->email);
    expect($messages[0]->getHtmlBody())->toContain('/verify-email/'.$user->id.'/');
    expect($messages[1]->getTo()[0]->getAddress())->toBe($user->email);
    expect($messages[1]->getHtmlBody())->toContain('/reset-password/local-test-token');
    expect($messages[2]->getTo()[0]->getAddress())->toBe('new@example.test');
    expect($messages[2]->getFrom()[0]->getAddress())->toBe('accounts@example.test');
    expect($messages[2]->getSubject())->toBe('Invitation to join Campaign Manager');
    expect($messages[2]->getHtmlBody())->toContain($url, 'Client Company');
    Http::assertNothingSent();
});

it('does not mail an invitation rolled back with its company transaction', function () {
    Notification::fake();
    DB::beginTransaction();
    try {
        $company = Organisation::create(['name' => 'Rolled back company']);
        app(MembershipService::class)->invite($company, 'new@example.test', 'viewer');
        Notification::assertNothingSent();
    } finally {
        DB::rollBack();
    }
    Notification::assertNothingSent();
    $this->assertDatabaseMissing('organisation_invitations', ['email' => 'new@example.test']);
});
