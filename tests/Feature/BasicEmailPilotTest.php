<?php

use App\Delivery\DeliveryMessage;
use App\Delivery\SendGridDeliveryProvider;
use App\Delivery\SubmissionResult;
use App\Exceptions\PilotSubmissionBlocked;
use App\Models\AuditLog;
use App\Models\DeliveryAttempt;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\CampaignService;
use App\Services\EmailContentService;
use App\Services\EmailStarterTemplates;
use App\Services\SendGridPilotGuard;
use App\Services\SendGridSubmissionService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function restrictedPilot(int $limit = 2): array
{
    [$u, $o, $c, $r] = sgFixture();
    config(['dispatch.pilot.enabled' => true, 'dispatch.pilot.daily_limit' => $limit, 'dispatch.pilot.recipients' => ['success@example.test', 'temporary@example.test', 'permanent@example.test'], 'dispatch.pilot.organisation_ids' => [(string) $o->id]]);

    return [$u, $o, $c, $r];
}
it('creates a separate basic email with reusable signature and preserves existing templates', function () {
    $u = User::factory()->create();
    $o = workspaceFor($u);
    $o->forceFill(['email_branding' => ['signature_text' => "Our friendly team\n020 0000 0000"]])->save();
    $this->actingAs($u)->post("/organisations/$o->id/templates/basic")->assertRedirect();
    $t = EmailTemplate::first();
    expect($t->name)->toBe('Basic email');
    expect($t->html)->toContain('Our friendly team', '{{sender_address}}', '{{unsubscribe_url}}')->not->toContain('<h1', 'data-email-button');
    $this->post("/organisations/$o->id/templates/basic")->assertRedirect();
    expect(EmailTemplate::count())->toBe(2);
    expect($t->fresh()->html)->toBe($t->html);
    $viewer = User::factory()->create();
    $o->members()->attach($viewer->id, ['role' => 'viewer']);
    $this->actingAs($viewer)->post("/organisations/$o->id/templates/basic")->assertForbidden();
});
it('generates safe link-aware plain text under company permissions', function () {
    $u = User::factory()->create();
    $o = workspaceFor($u);
    $response = $this->actingAs($u)->postJson("/organisations/$o->id/email-plain-text", ['html' => '<p>Hello {{first_name}}</p><script>bad()</script><p><a href="https://example.test/details">Details</a></p>']);
    $response->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    expect($response->json('text'))->toContain('Details (https://example.test/details)', '{{unsubscribe_url}}')->not->toContain('bad()');
    $other = workspaceFor(User::factory()->create());
    $this->postJson("/organisations/$other->id/email-plain-text", ['html' => '<p>Private</p>'])->assertForbidden();
});
it('keeps selected text formatting through sanitisation rendering and missing-name fallback', function () {
    $s = app(EmailContentService::class);
    $html = '<p><span style="font-family:Georgia,serif;font-size:24px;color:#cc0000;"><u>Important update</u></span></p><p>Hello {{first_name}}</p><a href="{{unsubscribe_url}}">Unsubscribe</a>';
    $clean = $s->clean($html);
    expect($clean)->toContain('font-family:Georgia, serif', 'font-size:24px', 'color:#cc0000', '<u>Important update</u>');
    expect($s->render($clean, ['first_name' => '', 'unsubscribe_url' => 'https://example.test/unsubscribe']))->toContain('Hello there', 'font-size:24px');
});
it('requires explicit pilot company recipients and valid budget while leaving simulation usable', function () {
    [$u,$o,$c,$r] = restrictedPilot();
    config(['dispatch.pilot.recipients' => [], 'dispatch.pilot.organisation_ids' => [], 'dispatch.pilot.daily_limit' => 0]);
    expect(app(SendGridPilotGuard::class)->configurationErrors($o->id))->toHaveCount(3);
    Http::preventStrayRequests();
    app(SendGridSubmissionService::class)->handle($r->id);
    expect($c->fresh()->status)->toBe('paused');
    expect(DeliveryAttempt::count())->toBe(0);
    Http::assertNothingSent();
});
it('blocks out-of-scope recipients and companies before provider HTTP', function () {
    [$u,$o,$c,$r] = restrictedPilot();
    $guard = app(SendGridPilotGuard::class);
    expect($guard->recipientAllowed(' Success@Example.Test '))->toBeTrue();
    expect($guard->recipientAllowed('other@example.test'))->toBeFalse();
    expect(fn () => $guard->reserve('other@example.test', 'attempt-one', $o->id))->toThrow(PilotSubmissionBlocked::class);
    expect(fn () => $guard->reserve('success@example.test', 'attempt-two', $o->id + 100))->toThrow(PilotSubmissionBlocked::class);
    expect(DB::table('sendgrid_pilot_reservations')->count())->toBe(0);
    Http::assertNothingSent();
});
it('uses a durable shared daily budget and pauses work without further HTTP at capacity', function () {
    [$u,$o,$c,$r] = restrictedPilot(2);
    Http::fake(['*' => Http::response([], 202)]);
    $service = app(SendGridSubmissionService::class);
    $service->handle($r->id);
    $service->handle($c->recipients()->where('email', 'temporary@example.test')->firstOrFail()->id);
    Cache::flush();
    $service->handle($c->recipients()->where('email', 'permanent@example.test')->firstOrFail()->id);
    Http::assertSentCount(2);
    expect(DB::table('sendgrid_pilot_budgets')->value('reserved'))->toBe(2);
    expect($c->fresh()->status)->toBe('paused');
    expect(app(SendGridPilotGuard::class)->remaining())->toBe(0);
});
it('does not refund uncertain attempts or permit a reserved correlation to replay', function () {
    [$u,$o,$c,$r] = restrictedPilot(2);
    $calls = 0;
    Http::fake(function () use (&$calls) {
        $calls++;
        throw new ConnectionException('Timeout fixture');
    });
    app(SendGridSubmissionService::class)->handle($r->id);
    expect($r->fresh()->status)->toBe('uncertain');
    expect(app(SendGridPilotGuard::class)->remaining())->toBe(1);
    $a = DeliveryAttempt::firstOrFail();
    $m = new DeliveryMessage($a->correlation_id, $r->email, 'from@example.test', 's', 'h', 't', null, false, true, false, false, [], '', $o->id, $o->sendGridCredentialFingerprint());
    expect(fn () => app(SendGridDeliveryProvider::class)->submit($m, 1))->toThrow(PilotSubmissionBlocked::class);
    expect($calls)->toBe(1);
});
it('blocks scheduling an audience containing recipients outside the pilot scope', function () {
    [$u,$o,$c,$r] = restrictedPilot();
    config(['dispatch.pilot.recipients' => ['success@example.test']]);
    expect(implode(' ', app(CampaignService::class)->preflight($c)['errors']))->toContain('outside the approved test list');
});

it('rejects demonstration copy for live campaigns while allowing safe simulation preparation', function () {
    [$u,$o,$c,$r] = restrictedPilot();
    $snapshot = $c->snapshot;
    $snapshot['html'] = app(EmailStarterTemplates::class)->basic()['html'];
    $c->update(['snapshot' => $snapshot]);
    expect(implode(' ', app(CampaignService::class)->preflight($c)['errors']))->toContain('sample headline or message');
});

it('starts a fresh UTC daily budget without allowing a reserved correlation to replay', function () {
    [$u, $o] = restrictedPilot(1);
    $guard = app(SendGridPilotGuard::class);
    $this->travelTo(now('UTC')->startOfDay()->addHours(23));
    $guard->reserve('success@example.test', 'old-correlation', $o->id);
    expect($guard->remaining())->toBe(0);
    $this->travel(2)->hours();
    expect($guard->remaining())->toBe(1);
    expect(fn () => $guard->reserve('success@example.test', 'old-correlation', $o->id))->toThrow(PilotSubmissionBlocked::class);
    expect($guard->remaining())->toBe(1);
    $guard->reserve('success@example.test', 'new-correlation', $o->id);
    expect($guard->remaining())->toBe(0);
});

it('records a specific pilot block reason without attempting submission', function () {
    [$u, $o, $c, $r] = restrictedPilot();
    config(['dispatch.pilot.recipients' => ['another@example.test']]);
    Http::fake();
    app(SendGridSubmissionService::class)->handle($r->id);
    Http::assertNothingSent();
    expect($c->fresh()->status)->toBe('paused');
    expect($r->fresh()->status)->toBe('pending');
    expect(AuditLog::where('action', 'campaign.pilot_blocked')->latest('id')->first()->metadata['diagnostic'])->toBe('pilot_recipient_not_allowed');
});

it('preserves unsent recipients on a late pilot hold without reversing cancellation', function () {
    [$u, $o, $c, $r] = restrictedPilot();
    $a = sgAttempt($r);
    $a->update(['outcome' => 'submitting']);
    $r->update(['status' => 'submitting']);
    $service = app(SendGridSubmissionService::class);
    $hold = new SubmissionResult('not_submitted', '', null, 'pilot_daily_limit_reached');
    $service->finish($a, $hold);
    expect($r->fresh()->status)->toBe('pending');
    expect($c->fresh()->status)->toBe('paused');
    $other = $c->recipients()->where('id', '!=', $r->id)->firstOrFail();
    $a2 = sgAttempt($other);
    $a2->update(['outcome' => 'submitting']);
    $other->update(['status' => 'submitting']);
    app(CampaignService::class)->cancel($c);
    $service->finish($a2, $hold);
    expect($c->fresh()->status)->toBe('cancelled');
    expect($other->fresh()->status)->toBe('cancelled');
});
