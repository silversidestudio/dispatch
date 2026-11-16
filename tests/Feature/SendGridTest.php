<?php

use App\Delivery\DeliveryMessage;
use App\Delivery\SendGridDeliveryProvider;
use App\Jobs\ProcessSendGridWebhook;
use App\Jobs\PumpCampaigns;
use App\Models\Contact;
use App\Models\DeliveryAttempt;
use App\Models\DeliveryEvent;
use App\Models\Organisation;
use App\Models\SendingIdentity;
use App\Models\WebhookBatch;
use App\Models\WebhookReceipt;
use App\Services\CampaignAnalytics;
use App\Services\CampaignService;
use App\Services\EligibilityService;
use App\Services\SendGridSubmissionService;
use App\Services\UnsubscribeService;
use App\Services\WebhookService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

function sgFixture(): array
{
    Queue::fake();
    Http::preventStrayRequests();
    config(['dispatch.pilot.enabled' => false, 'dispatch.provider' => 'sendgrid', 'dispatch.sendgrid.enabled' => true, 'dispatch.sendgrid.api_key' => 'test-key-not-real', 'dispatch.sendgrid.live_enabled' => true, 'dispatch.sendgrid.webhook_key' => 'test-key', 'app.url' => 'https://dispatch.example.test']);
    [$u,$o,$c] = preparedCampaign();
    $o->forceFill(['sendgrid_api_key' => 'test-key-not-real', 'sendgrid_webhook_key' => 'test-key', 'open_tracking_enabled' => true, 'click_tracking_enabled' => true, 'tracking_reviewed_at' => now(), 'tracking_review_evidence' => 'Test approval fixture only'])->save();
    SendingIdentity::whereKey($c->sending_identity_id)->update(['provider_authorised' => true, 'verification_status' => 'verified', 'verified_at' => now()]);
    $c->update(['delivery_mode' => 'sendgrid_live', 'open_tracking' => true, 'click_tracking' => true]);
    $s = app(CampaignService::class);
    $s->ready($c);
    $s->schedule($c->fresh(), now(), true);
    (new PumpCampaigns)->handle();
    $r = $c->recipients()->where('email', 'success@example.test')->first();

    return [$u, $o, $c->fresh(), $r];
}
function sgAttempt($r): DeliveryAttempt
{
    return DeliveryAttempt::create(['organisation_id' => $r->organisation_id, 'campaign_recipient_id' => $r->id, 'number' => 1, 'provider' => 'sendgrid', 'correlation_id' => (string) Str::uuid(), 'outcome' => 'accepted', 'provider_message_id' => 'provider-message', 'started_at' => now(), 'finished_at' => now()]);
}
function sgEvent($a, string $type, array $extra = []): array
{
    return ['sg_event_id' => (string) Str::uuid(), 'dispatch_attempt' => $a->correlation_id, 'event' => $type, 'timestamp' => time(), 'email' => 'success@example.test', 'sg_message_id' => 'provider-message', ...$extra];
}
function ingestSg($event): void
{
    $s = app(WebhookService::class);
    $s->ingest($s->normalise($event));
}
it('defaults to simulation with no live sending or tracking', function () {
    expect(config('dispatch.provider'))->toBe('simulation');
    expect(config('dispatch.sendgrid.enabled'))->toBeFalse();
    expect(config('dispatch.sendgrid.live_enabled'))->toBeFalse();
    $o = Organisation::create(['name' => 'Safe default']);
    expect((bool) $o->fresh()->open_tracking_enabled)->toBeFalse();
});
it('builds a v3 sandbox request with explicit tracking and unsubscribe headers', function () {
    Http::preventStrayRequests();
    config(['dispatch.sendgrid.enabled' => true, 'dispatch.sendgrid.api_key' => 'test', 'dispatch.sendgrid.live_enabled' => false]);
    Http::fake(['api.sendgrid.com/v3/mail/send' => Http::response([], 200)]);
    $o = Organisation::create(['name' => 'Sandbox']);
    $o->forceFill(['sendgrid_api_key' => 'company-test'])->save();
    $m = new DeliveryMessage('correlation', 'to@example.test', 'from@example.test', 'Subject', '<p>Safe</p>', 'Safe', null, true, false, false, false, ['List-Unsubscribe' => '<https://example.test/one-click/token>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'], organisationId: $o->id, credentialFingerprint: $o->sendGridCredentialFingerprint());
    $result = app(SendGridDeliveryProvider::class)->submit($m, 1);
    expect($result->outcome)->toBe('sandbox_validated');
    Http::assertSent(fn ($r) => $r->url() === 'https://api.sendgrid.com/v3/mail/send' && $r->hasHeader('Authorization', 'Bearer company-test') && $r['mail_settings']['sandbox_mode']['enable'] && $r['tracking_settings']['subscription_tracking']['enable'] === false && $r['tracking_settings']['open_tracking']['enable'] === false && $r['personalizations'][0]['custom_args']['dispatch_attempt'] === 'correlation');
});
it('classifies provider responses conservatively', function (int $code, string $outcome) {
    config(['dispatch.pilot.enabled' => false, 'dispatch.sendgrid.enabled' => true, 'dispatch.sendgrid.api_key' => 'test', 'dispatch.sendgrid.live_enabled' => true]);
    Http::fake(['*' => Http::response([], $code, ['X-Message-Id' => 'id', 'Retry-After' => '20'])]);
    $o = Organisation::create(['name' => 'Live test']);
    $o->forceFill(['sendgrid_api_key' => 'company-test'])->save();
    $m = new DeliveryMessage('id', 'to@example.test', 'from@example.test', 'Subject', 'HTML', 'Text', null, false, true, organisationId: $o->id, credentialFingerprint: $o->sendGridCredentialFingerprint());
    expect(app(SendGridDeliveryProvider::class)->submit($m, 1)->outcome)->toBe($outcome);
})->with([[202, 'accepted'], [401, 'rejected'], [403, 'rejected'], [400, 'rejected'], [429, 'deferred'], [500, 'uncertain'], [408, 'uncertain']]);
it('blocks unapproved live calls before any request', function () {
    Http::preventStrayRequests();
    config(['dispatch.sendgrid.enabled' => true, 'dispatch.sendgrid.api_key' => 'test']);
    $o = Organisation::create(['name' => 'Live disabled']);
    $o->forceFill(['sendgrid_api_key' => 'company-test'])->save();
    $message = new DeliveryMessage('id', 'to@example.test', 'from@example.test', 's', 'h', 't', null, false, true, organisationId: $o->id, credentialFingerprint: $o->sendGridCredentialFingerprint());
    expect(fn () => app(SendGridDeliveryProvider::class)->submit($message, 1))->toThrow(RuntimeException::class, 'Live sending is disabled or unauthorised.');
    Http::assertNothingSent();
});
it('durably records accepted attempts and never replays them', function () {
    [$u,$o,$c,$r] = sgFixture();
    Http::fake(['*' => Http::response([], 202, ['X-Message-Id' => 'sg-id'])]);
    $s = app(SendGridSubmissionService::class);
    $s->handle($r->id);
    $s->handle($r->id);
    expect($r->fresh()->status)->toBe('accepted');
    expect(DeliveryAttempt::count())->toBe(1);
    Http::assertSentCount(1);
    expect(DeliveryAttempt::first()->correlation_id)->not->toBeNull();
});
it('holds ambiguous timeouts and job replays instead of resending', function () {
    [$u,$o,$c,$r] = sgFixture();
    $calls = 0;
    Http::fake(function () use (&$calls) {
        $calls++;
        throw new ConnectionException('Sensitive transport details');
    });
    $s = app(SendGridSubmissionService::class);
    $s->handle($r->id);
    $s->handle($r->id);
    expect($r->fresh()->status)->toBe('uncertain');
    expect(DeliveryAttempt::first()->diagnostic_code)->toBe('network_outcome_unknown');
    expect($calls)->toBe(1);
});
it('rechecks eligibility identity and suspension before external work', function () {
    [$u,$o,$c,$r] = sgFixture();
    app(EligibilityService::class)->suppress(Contact::find($r->contact_id), 'unsubscribed');
    app(SendGridSubmissionService::class)->handle($r->id);
    Http::assertNothingSent();
    expect($r->fresh()->status)->toBe('suppressed');
});
it('blocks stale unverified identities and requires live confirmation', function () {
    [$u,$o,$c,$r] = sgFixture();
    SendingIdentity::whereKey($c->sending_identity_id)->update(['verification_status' => 'unverified']);
    app(SendGridSubmissionService::class)->handle($r->id);
    Http::assertNothingSent();
    expect($r->fresh()->status)->toBe('deferred');
});
it('pauses and resumes campaign processing without altering snapshots', function () {
    [$u,$o,$c,$r] = sgFixture();
    $s = app(CampaignService::class);
    $snapshot = $c->snapshot;
    $s->pause($c);
    app(SendGridSubmissionService::class)->handle($r->id);
    Http::assertNothingSent();
    $s->resume($c->fresh());
    expect($c->fresh()->status)->toBe('processing');
    expect($c->fresh()->snapshot)->toBe($snapshot);
    $s->cancel($c->fresh());
    app(SendGridSubmissionService::class)->handle($r->id);
    Http::assertNothingSent();
});
it('verifies raw ECDSA signatures and rejects stale altered or unsigned bodies', function () {
    Queue::fake();
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    $details = openssl_pkey_get_details($key);
    $o = Organisation::create(['name' => 'Webhook test']);
    $o->forceFill(['sendgrid_webhook_key' => $details['key']])->save();
    $body = '[{"event":"processed","sg_event_id":"sample","dispatch_attempt":"unmatched"}]';
    $ts = (string) time();
    openssl_sign($ts.$body, $sig, $key, OPENSSL_ALGO_SHA256);
    $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_TIMESTAMP' => $ts, 'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_SIGNATURE' => base64_encode($sig)];
    $this->call('POST', "/webhooks/sendgrid/$o->id", [], [], [], $headers, $body)->assertStatus(202);
    $this->call('POST', "/webhooks/sendgrid/$o->id", [], [], [], $headers, $body.' ')->assertUnauthorized();
    $headers['HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_TIMESTAMP'] = (string) (time() - 600);
    $this->call('POST', "/webhooks/sendgrid/$o->id", [], [], [], $headers, $body)->assertUnauthorized();
    (new ProcessSendGridWebhook(WebhookBatch::first()->id))->handle(app(WebhookService::class));
    expect(WebhookReceipt::first()->status)->toBe('quarantined');
});
it('deduplicates signed events and resists out of order status regression', function () {
    [$u,$o,$c,$r] = sgFixture();
    $a = sgAttempt($r);
    $e = sgEvent($a, 'delivered');
    ingestSg($e);
    ingestSg($e);
    ingestSg(sgEvent($a, 'deferred', ['timestamp' => time() - 10]));
    expect(DeliveryEvent::where('type', 'delivered')->count())->toBe(1);
    expect($r->fresh()->delivery_status)->toBe('delivered');
});
it('quarantines mismatched recipient correlation rather than crossing tenants', function () {
    [$u,$o,$c,$r] = sgFixture();
    $a = sgAttempt($r);
    ingestSg(sgEvent($a, 'delivered', ['email' => 'other@example.test', 'organisation_id' => 999]));
    expect(WebhookReceipt::first()->status)->toBe('quarantined');
    expect(DeliveryEvent::where('type', 'delivered')->count())->toBe(0);
});
it('counts unique and total engagement separately and strips sensitive URLs', function () {
    [$u,$o,$c,$r] = sgFixture();
    $a = sgAttempt($r);
    foreach (['processed', 'delivered', 'open', 'open', 'click', 'click'] as $type) {
        ingestSg(sgEvent($a, $type, ['url' => 'https://example.test/private-path?token=SECRET']));
    }ingestSg(sgEvent($a, 'click', ['url' => 'https://dispatch.example.test/one-click/SECRET']));
    $s = app(CampaignAnalytics::class)->summary($c);
    expect($s['counts']['delivered'])->toBe(1);
    expect($s['counts']['unique_opens'])->toBe(1);
    expect($s['counts']['total_opens'])->toBe(2);
    expect($s['counts']['unique_clicks'])->toBe(1);
    expect($s['counts']['total_clicks'])->toBe(2);
    expect($s['rates']['delivery'])->toBe(100.0);
    expect($s['links'][0]['url'])->not->toContain('SECRET', 'private-path');
});
it('ignores tracking events disabled in the immutable campaign snapshot', function () {
    [$u,$o,$c,$r] = sgFixture();
    $snapshot = $c->snapshot;
    $snapshot['open_tracking'] = false;
    $snapshot['click_tracking'] = false;
    $c->update(['snapshot' => $snapshot]);
    $a = sgAttempt($r);
    ingestSg(sgEvent($a, 'open'));
    ingestSg(sgEvent($a, 'click', ['url' => 'https://example.test/']));
    expect(DeliveryEvent::whereIn('type', ['open', 'click'])->count())->toBe(0);
    expect(app(CampaignAnalytics::class)->summary($c->fresh())['rates']['unique_open'])->toBeNull();
});
it('suppresses complaints hard bounces and provider unsubscribes', function (string $type, string $reason) {
    [$u,$o,$c,$r] = sgFixture();
    $a = sgAttempt($r);
    ingestSg(sgEvent($a, $type, ['type' => 'bounce']));
    $this->assertDatabaseHas('suppressions', ['organisation_id' => $o->id, 'email' => $r->email, 'reason' => $reason]);
})->with([['spamreport', 'complaint'], ['bounce', 'hard_bounce'], ['unsubscribe', 'unsubscribed']]);
it('processes signed one click POST without a session while GET is harmless', function () {
    [$u,$o,$c,$r] = sgFixture();
    $contact = Contact::find($r->contact_id);
    $url = app(UnsubscribeService::class)->oneClickUrl($contact, $r);
    $this->get($url)->assertOk();
    expect(app(EligibilityService::class)->eligible($contact))->toBeTrue();
    $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertNoContent();
    $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertNoContent();
    expect(app(EligibilityService::class)->eligible($contact))->toBeFalse();
    expect(DeliveryEvent::where('type', 'unsubscribe')->count())->toBe(1);
});

it('does not use the platform notification key for a company without credentials', function () {
    Http::preventStrayRequests();
    config(['dispatch.sendgrid.enabled' => true, 'dispatch.sendgrid.api_key' => 'platform-notification-key']);
    $o = Organisation::create(['name' => 'Unconfigured company']);
    $message = new DeliveryMessage('id', 'to@example.test', 'from@example.test', 's', 'h', 't', organisationId: $o->id);
    expect(fn () => app(SendGridDeliveryProvider::class)->submit($message, 1))->toThrow(RuntimeException::class, 'SendGrid is not configured.');
    Http::assertNothingSent();
});

it('stores company secrets encrypted and hidden, rejects cross-company changes and invalidates old snapshots', function () {
    [$u, $o, $c, $r] = sgFixture();
    $other = Organisation::create(['name' => 'Other company']);
    $this->actingAs($u)->patch("/organisations/$other->id/sendgrid-credentials", ['sendgrid_api_key' => 'blocked'])->assertForbidden();
    $this->patch("/organisations/$o->id/sendgrid-credentials", ['sendgrid_api_key' => 'updated-key', 'sendgrid_webhook_key' => 'updated-webhook'])->assertRedirect();
    $fresh = $o->fresh();
    expect($fresh->sendgrid_api_key)->toBe('updated-key');
    expect($fresh->getRawOriginal('sendgrid_api_key'))->not->toBe('updated-key');
    expect($fresh->toArray())->not->toHaveKeys(['sendgrid_api_key', 'sendgrid_webhook_key']);
    expect(SendingIdentity::find($c->sending_identity_id)->provider_authorised)->toBeFalse();
    app(SendGridSubmissionService::class)->handle($r->id);
    Http::assertNothingSent();
    expect($r->fresh()->status)->toBe('deferred');
});

it('binds signed webhook batches to the endpoint company even when a different company correlation is supplied', function () {
    [$u, $o, $c, $r] = sgFixture();
    $attempt = sgAttempt($r);
    $other = Organisation::create(['name' => 'Other company']);
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    $other->forceFill(['sendgrid_webhook_key' => openssl_pkey_get_details($key)['key']])->save();
    $body = json_encode([sgEvent($attempt, 'delivered', ['organisation_id' => $o->id])]);
    $ts = (string) time();
    openssl_sign($ts.$body, $sig, $key, OPENSSL_ALGO_SHA256);
    $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_TIMESTAMP' => $ts, 'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_SIGNATURE' => base64_encode($sig)];
    $this->call('POST', "/webhooks/sendgrid/$o->id", [], [], [], $headers, $body)->assertUnauthorized();
    $this->call('POST', "/webhooks/sendgrid/$other->id", [], [], [], $headers, $body)->assertStatus(202);
    (new ProcessSendGridWebhook(WebhookBatch::first()->id))->handle(app(WebhookService::class));
    expect(WebhookReceipt::first()->status)->toBe('quarantined');
    $this->assertDatabaseMissing('delivery_events', ['campaign_recipient_id' => $r->id, 'type' => 'delivered']);
});

it('matches analytics date and event filters against the same verified event', function () {
    [$u, $o, $c, $r] = sgFixture();
    $a = sgAttempt($r);
    ingestSg(sgEvent($a, 'delivered', ['timestamp' => now()->subDays(2)->timestamp]));
    ingestSg(sgEvent($a, 'open'));
    $url = "/organisations/$o->id/campaigns/$c->id/analytics?from=".now('UTC')->toDateString();
    $this->actingAs($u)->get($url.'&status=delivered')->assertInertia(fn (AssertableInertia $p) => $p->where('recipients.total', 0));
    $this->get($url.'&status=open')->assertInertia(fn (AssertableInertia $p) => $p->where('recipients.total', 1)->where('analytics.tracking.open', true));
    ingestSg(sgEvent($a, 'group_unsubscribe'));
    $this->get($url.'&status=unsubscribe')->assertInertia(fn (AssertableInertia $p) => $p->where('recipients.total', 1));
});

it('reports signed company feedback through the queued path without double-counting engagement', function () {
    [$u, $o, $c, $r] = sgFixture();
    $a = sgAttempt($r);
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    $o->forceFill(['sendgrid_webhook_key' => openssl_pkey_get_details($key)['key']])->save();
    $open = sgEvent($a, 'open');
    $body = json_encode([
        sgEvent($a, 'delivered'),
        sgEvent($a, 'deferred', ['timestamp' => time() - 20]),
        $open,
        $open,
        sgEvent($a, 'open'),
        sgEvent($a, 'click', ['url' => 'https://example.test/offer?token=private-secret']),
    ]);
    $ts = (string) time();
    openssl_sign($ts.$body, $sig, $key, OPENSSL_ALGO_SHA256);
    $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_TIMESTAMP' => $ts, 'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_SIGNATURE' => base64_encode($sig)];
    $path = "/webhooks/sendgrid/$o->id";
    $this->call('POST', $path, [], [], [], $headers, $body)->assertAccepted();
    (new ProcessSendGridWebhook(WebhookBatch::sole()->id))->handle(app(WebhookService::class));
    $this->call('POST', $path, [], [], [], $headers, $body)->assertAccepted();

    $summary = app(CampaignAnalytics::class)->summary($c);
    expect($r->fresh()->delivery_status)->toBe('delivered');
    expect($summary['counts'])->toMatchArray(['delivered' => 1, 'unique_opens' => 1, 'total_opens' => 2, 'unique_clicks' => 1, 'total_clicks' => 1]);
    expect($summary['links'][0]['url'])->toStartWith('https://example.test / link-')->not->toContain('private-secret');
    expect(WebhookBatch::count())->toBe(1);
});
