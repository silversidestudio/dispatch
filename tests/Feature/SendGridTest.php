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

function sgFixture(): array
{
    Queue::fake();
    Http::preventStrayRequests();
    config(['dispatch.pilot.enabled' => false, 'dispatch.provider' => 'sendgrid', 'dispatch.sendgrid.enabled' => true, 'dispatch.sendgrid.api_key' => 'test-key-not-real', 'dispatch.sendgrid.live_enabled' => true, 'dispatch.sendgrid.webhook_key' => 'test-key', 'app.url' => 'https://dispatch.example.test']);
    [$u,$o,$c] = preparedCampaign();
    $o->forceFill(['open_tracking_enabled' => true, 'click_tracking_enabled' => true, 'tracking_reviewed_at' => now(), 'tracking_review_evidence' => 'Test approval fixture only'])->save();
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
    $m = new DeliveryMessage('correlation', 'to@example.test', 'from@example.test', 'Subject', '<p>Safe</p>', 'Safe', null, true, false, false, false, ['List-Unsubscribe' => '<https://example.test/one-click/token>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click']);
    $result = app(SendGridDeliveryProvider::class)->submit($m, 1);
    expect($result->outcome)->toBe('sandbox_validated');
    Http::assertSent(fn ($r) => $r->url() === 'https://api.sendgrid.com/v3/mail/send' && $r['mail_settings']['sandbox_mode']['enable'] && $r['tracking_settings']['subscription_tracking']['enable'] === false && $r['tracking_settings']['open_tracking']['enable'] === false && $r['personalizations'][0]['custom_args']['dispatch_attempt'] === 'correlation');
});
it('classifies provider responses conservatively', function (int $code, string $outcome) {
    config(['dispatch.pilot.enabled' => false, 'dispatch.sendgrid.enabled' => true, 'dispatch.sendgrid.api_key' => 'test', 'dispatch.sendgrid.live_enabled' => true]);
    Http::fake(['*' => Http::response([], $code, ['X-Message-Id' => 'id', 'Retry-After' => '20'])]);
    $m = new DeliveryMessage('id', 'to@example.test', 'from@example.test', 'Subject', 'HTML', 'Text', null, false, true);
    expect(app(SendGridDeliveryProvider::class)->submit($m, 1)->outcome)->toBe($outcome);
})->with([[202, 'accepted'], [401, 'rejected'], [403, 'rejected'], [400, 'rejected'], [429, 'deferred'], [500, 'uncertain'], [408, 'uncertain']]);
it('blocks unapproved live calls before any request', function () {
    Http::preventStrayRequests();
    config(['dispatch.sendgrid.enabled' => true, 'dispatch.sendgrid.api_key' => 'test']);
    expect(fn () => app(SendGridDeliveryProvider::class)->submit(new DeliveryMessage('id', 'to@example.test', 'from@example.test', 's', 'h', 't', null, false, true), 1))->toThrow(RuntimeException::class);
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
    config(['dispatch.sendgrid.webhook_key' => $details['key']]);
    $body = '[{"event":"processed","sg_event_id":"sample","dispatch_attempt":"unmatched"}]';
    $ts = (string) time();
    openssl_sign($ts.$body, $sig, $key, OPENSSL_ALGO_SHA256);
    $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_TIMESTAMP' => $ts, 'HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_SIGNATURE' => base64_encode($sig)];
    $this->call('POST', '/webhooks/sendgrid', [], [], [], $headers, $body)->assertStatus(202);
    $this->call('POST', '/webhooks/sendgrid', [], [], [], $headers, $body.' ')->assertUnauthorized();
    $headers['HTTP_X_TWILIO_EMAIL_EVENT_WEBHOOK_TIMESTAMP'] = (string) (time() - 600);
    $this->call('POST', '/webhooks/sendgrid', [], [], [], $headers, $body)->assertUnauthorized();
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
