<?php

use App\Jobs\DeliverRecipient;
use App\Jobs\PumpCampaigns;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\DeliveryAttempt;
use App\Models\MailingList;
use App\Models\User;
use App\Services\CampaignAnalytics;
use App\Services\CampaignService;
use App\Services\EligibilityService;
use App\Services\EmailContentService;
use App\Services\ProviderReadiness;
use App\Services\SendGridSubmissionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

it('does not upgrade a legacy simulation snapshot when the default provider changes', function () {
    Queue::fake();
    [$u,$o,$c] = preparedCampaign();
    $s = app(CampaignService::class);
    $s->ready($c);
    $s->schedule($c->fresh(), now());
    config(['dispatch.provider' => 'sendgrid', 'dispatch.sendgrid.enabled' => true, 'dispatch.sendgrid.api_key' => 'test', 'dispatch.sendgrid.live_enabled' => true]);
    Http::preventStrayRequests();
    (new PumpCampaigns)->handle();
    $r = $c->recipients()->where('email', 'success@example.test')->first();
    (new DeliverRecipient($r->id))->handle($s);
    Http::assertNothingSent();
    expect($r->fresh()->status)->toBe('accepted');
});
it('requires explicit live confirmation and reviewed tracking before scheduling', function () {
    [$u,$o,$c,$r] = sgFixture();
    $copy = $c->replicate(['snapshot', 'scheduled_at', 'live_authorised_at']);
    $copy->status = 'ready';
    $copy->save();
    expect(fn () => app(CampaignService::class)->schedule($copy, now()))->toThrow(ValidationException::class);
    $o->forceFill(['tracking_reviewed_at' => null])->save();
    expect(app(ProviderReadiness::class)->errors($c, true))->not->toBeEmpty();
});
it('respects configured throttling and provider Retry After without extra submissions', function () {
    [$u,$o,$c,$r] = sgFixture();
    Http::fake(['*' => Http::response([], 429, ['Retry-After' => '120'])]);
    $s = app(SendGridSubmissionService::class);
    $s->handle($r->id);
    $s->handle($r->id);
    Http::assertSentCount(1);
    expect($r->fresh()->next_attempt_at->gt(now()->addSeconds(100)))->toBeTrue();
    config(['dispatch.sendgrid.per_minute' => 1]);
    $other = $c->recipients()->where('id', '!=', $r->id)->first();
    $s->handle($other->id);
    Http::assertSentCount(1);
});
it('uses signed feedback when it arrives before the API acknowledgement', function () {
    [$u,$o,$c,$r] = sgFixture();
    Http::fake(function () use ($r) {
        $a = DeliveryAttempt::where('campaign_recipient_id', $r->id)->firstOrFail();
        ingestSg(sgEvent($a, 'delivered'));

        return Http::response([], 202, ['X-Message-Id' => 'http-id']);
    });
    app(SendGridSubmissionService::class)->handle($r->id);
    expect($r->fresh()->delivery_status)->toBe('delivered');
    expect(DeliveryAttempt::first()->http_status)->toBe(202);
    expect(DeliveryAttempt::first()->outcome)->toBe('accepted');
});
it('allows only evidenced uncertain reconciliation and blocks it after signed evidence', function () {
    [$u,$o,$c,$r] = sgFixture();
    Http::fake(['*' => Http::response([], 500)]);
    app(SendGridSubmissionService::class)->handle($r->id);
    $a = DeliveryAttempt::first();
    $this->actingAs($u)->post("/organisations/$o->id/attempts/$a->id/reconcile", ['decision' => 'closed_without_retry', 'confirmation' => 'RECONCILE', 'evidence' => 'Provider investigation remains inconclusive.'])->assertRedirect();
    expect($r->fresh()->status)->toBe('failed');
});
it('does not grant platform administrators analytics or private recipient exports', function () {
    [$u,$o,$c,$r] = sgFixture();
    $a = User::factory()->create();
    $a->is_platform_admin = true;
    $a->save();
    $this->actingAs($a)->get("/organisations/$o->id/campaigns/$c->id/analytics")->assertForbidden();
    $this->get("/organisations/$o->id/campaigns/$c->id/analytics/export")->assertForbidden();
    $b = workspaceFor($a);
    $this->get("/organisations/$b->id/campaigns/$c->id/activity/$r->id")->assertNotFound();
});
it('serves analytics date filters comparison recipient timeline and safe CSV export', function () {
    [$u,$o,$c,$r] = sgFixture();
    $a = sgAttempt($r);
    ingestSg(sgEvent($a, 'delivered'));
    ingestSg(sgEvent($a, 'click', ['url' => 'https://example.test/account/SECRET?auth=TOKEN']));
    $this->actingAs($u)->get("/organisations/$o->id/campaigns/$c->id/analytics")->assertInertia(fn (Assert $p) => $p->where('analytics.counts.delivered', 1));
    $this->get("/organisations/$o->id/campaigns/$c->id/activity/$r->id")->assertOk();
    $this->get("/organisations/$o->id/campaign-comparison")->assertOk();
    $this->get("/organisations/$o->id/campaigns/$c->id/analytics?to=".now()->toDateString())->assertOk();
    $filtered = $this->get("/organisations/$o->id/campaigns/$c->id/analytics/export?status=delivered")->streamedContent();
    expect($filtered)->toContain('delivered')->not->toContain('click');
    $csv = $this->get("/organisations/$o->id/campaigns/$c->id/analytics/export")->streamedContent();
    expect($csv)->not->toContain('SECRET', 'TOKEN');
    $s = app(CampaignAnalytics::class)->summary($c, now()->addDay()->toDateString(), now()->addDays(2)->toDateString());
    expect($s['counts']['delivered'])->toBe(0);
    expect($s['rates']['delivery'])->toBeNull();
});
it('treats blocked bounce feedback separately from hard bounces', function () {
    [$u,$o,$c,$r] = sgFixture();
    $a = sgAttempt($r);
    ingestSg(sgEvent($a, 'bounce', ['type' => 'blocked']));
    expect($r->fresh()->delivery_status)->toBe('blocked');
    expect(app(EligibilityService::class)->eligible(Contact::find($r->contact_id)))->toBeTrue();
});
it('exports and erases contact data with suppression retention and snapshot safeguards', function () {
    [$u,$o,$c,$r] = sgFixture();
    $this->actingAs($u)->post("/organisations/$o->id/contacts/$r->contact_id/erase", ['confirmation' => 'ERASE'])->assertSessionHasErrors('privacy');
    app(CampaignService::class)->cancel($c);
    $this->get("/organisations/$o->id/contacts/$r->contact_id/privacy-export")->assertOk();
    $this->post("/organisations/$o->id/contacts/$r->contact_id/erase", ['confirmation' => 'ERASE'])->assertRedirect();
    expect(Contact::find($r->contact_id)->email)->toContain('@deleted.invalid');
    $this->assertDatabaseHas('suppressions', ['organisation_id' => $o->id, 'email' => 'success@example.test']);
});
it('provides five sanitised starters and preserves unsubscribe tracking exclusion', function () {
    $s = app(EmailContentService::class);
    expect($s->starters())->toHaveCount(5);
    foreach ($s->starters() as $t) {
        $html = $s->render($t['html'], ['first_name' => '<unsafe>', 'sender_name' => 'Company', 'sender_address' => 'Address', 'unsubscribe_url' => 'https://example.test/one-click/token']);
        expect($html)->toContain('clicktracking="off"')->not->toContain('<unsafe>');
    }
});
it('fails production requests closed for debug HTTP or insecure cookies', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.debug' => true, 'app.url' => 'https://example.test', 'session.secure' => true]);
    $this->get('/login')->assertStatus(503);
    config(['app.debug' => false]);
    $this->get('/login')->assertStatus(400);
    $this->get('https://example.test/login')->assertOk()->assertHeader('Strict-Transport-Security', 'max-age=31536000')->assertHeader('Referrer-Policy', 'same-origin');
});
it('sends framework account notifications only through an explicitly authorised mocked auth channel', function () {
    Http::preventStrayRequests();
    $u = User::factory()->unverified()->create();
    config(['dispatch.pilot.enabled' => false, 'dispatch.sendgrid.auth_enabled' => true, 'dispatch.sendgrid.auth_from' => 'accounts@example.test', 'dispatch.sendgrid.enabled' => true, 'dispatch.sendgrid.live_enabled' => true, 'dispatch.sendgrid.api_key' => 'test']);
    Http::fake(['*' => Http::response([], 202)]);
    $u->sendEmailVerificationNotification();
    $u->sendPasswordResetNotification('test-reset-token');
    Http::assertSentCount(2);
    Http::assertSent(fn ($r) => $r['tracking_settings']['open_tracking']['enable'] === false && $r['from']['email'] === 'accounts@example.test');
});
it('prepares only an eligible tenant-scoped single-recipient simulation or sandbox test without submission', function () {
    Queue::fake();
    Http::preventStrayRequests();
    [$u, $o, $c] = preparedCampaign();
    $base = "/organisations/$o->id/campaigns/$c->id/prepare-test";
    $this->actingAs($u)->post($base, ['email' => 'success@example.test', 'mode' => 'simulation', 'confirmation' => 'PREPARE'])->assertRedirect();
    $copy = Campaign::where('organisation_id', $o->id)->latest('id')->first();
    expect($copy->id)->not->toBe($c->id);
    expect($copy->status)->toBe('draft');
    expect($copy->snapshot)->toBeNull();
    expect($copy->live_authorised_at)->toBeNull();
    expect($copy->delivery_mode)->toBe('simulation');
    expect(MailingList::findOrFail($copy->audience_id)->contacts()->pluck('email')->all())->toBe(['success@example.test']);
    $this->post($base, ['email' => 'success@example.test', 'mode' => 'sendgrid_sandbox', 'confirmation' => 'PREPARE'])->assertRedirect();
    expect(Campaign::where('organisation_id', $o->id)->latest('id')->value('delivery_mode'))->toBe('sendgrid_sandbox');
    $this->post($base, ['email' => 'success@example.test', 'mode' => 'sendgrid_live', 'confirmation' => 'PREPARE'])->assertSessionHasErrors('mode');
    $this->post($base, ['email' => 'outside@example.test', 'mode' => 'simulation', 'confirmation' => 'PREPARE'])->assertSessionHasErrors('email');
    Http::assertNothingSent();
    Queue::assertNothingPushed();
});
