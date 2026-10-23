<?php

use App\Jobs\DeliverRecipient;
use App\Jobs\PumpCampaigns;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\DeliveryAttempt;
use App\Models\DeliveryEvent;
use App\Models\MailingList;
use App\Models\SendingIdentity;
use App\Models\User;
use App\Services\CampaignService;
use App\Services\EligibilityService;
use App\Services\EmailContentService;
use App\Services\UnsubscribeService;
use Illuminate\Support\Facades\Queue;

function preparedCampaign(): array
{
    $u = User::factory()->create();
    $o = workspaceFor($u);
    $o->update(['postal_address' => '1 Example Street, London']);
    $l = MailingList::create(['organisation_id' => $o->id, 'name' => 'Newsletter']);
    foreach (['success@example.test', 'temporary@example.test', 'permanent@example.test', 'unknown@example.test'] as $email) {
        $c = contactFor($o, $email);
        $l->contacts()->attach($c->id);
        if (! str_starts_with($email, 'unknown')) {
            app(EligibilityService::class)->record($c, ['basis' => 'consent', 'evidence' => 'Explicit consent evidence for this company', 'method' => 'Signup', 'recorded_at' => now()]);
        }
    }
    $i = SendingIdentity::create(['organisation_id' => $o->id, 'name' => 'Example sender', 'email' => 'sender@example.test', 'domain' => 'example.test']);
    $c = Campaign::create(['organisation_id' => $o->id, 'created_by' => $u->id, 'name' => 'Campaign', 'subject' => 'News', 'html' => '<h1>Hello {{first_name}}</h1><p>{{sender_address}}</p><a href="{{unsubscribe_url}}">Unsubscribe</a>', 'text_body' => 'News. Unsubscribe: {{unsubscribe_url}}', 'sending_identity_id' => $i->id, 'audience_type' => 'list', 'audience_id' => $l->id, 'status' => 'draft']);

    return [$u, $o, $c];
}
it('requires valid preflight and transitions then freezes content and snapshots eligible recipients', function () {
    Queue::fake();
    [$u,$o,$c] = preparedCampaign();
    $this->actingAs($u);
    $base = "/organisations/$o->id/campaigns/$c->id";
    $this->post($base.'/schedule')->assertSessionHasErrors('campaign');
    $this->post($base.'/ready')->assertRedirect();
    $this->post($base.'/schedule')->assertRedirect();
    expect($c->fresh()->status)->toBe('scheduled');
    expect($c->recipients()->count())->toBe(3);
    expect($c->fresh()->excluded_count)->toBe(1);
    $this->get($base.'/edit')->assertSessionHasErrors('campaign');
    $this->post($base.'/schedule')->assertSessionHasErrors('campaign');
    expect($c->recipients()->count())->toBe(3);
});
it('simulates successes permanent failure retries and replay without duplicate submissions', function () {
    Queue::fake();
    [$u,$o,$c] = preparedCampaign();
    $s = app(CampaignService::class);
    $s->ready($c);
    $s->schedule($c->fresh(), now());
    (new PumpCampaigns)->handle();
    foreach ($c->recipients()->get() as $r) {
        (new DeliverRecipient($r->id))->handle($s);
    }
    expect(DeliveryAttempt::count())->toBe(3);
    expect($c->recipients()->where('status', 'deferred')->count())->toBe(1);
    foreach ($c->recipients()->get() as $r) {
        (new DeliverRecipient($r->id))->handle($s);
    }expect(DeliveryAttempt::count())->toBe(3);
    $this->travel(31)->seconds();
    foreach ($c->recipients()->get() as $r) {
        (new DeliverRecipient($r->id))->handle($s);
    }
    expect(DeliveryAttempt::count())->toBe(4);
    expect($c->fresh()->status)->toBe('completed');
    expect($c->recipients()->where('status', 'accepted')->count())->toBe(2);
    expect($c->recipients()->where('status', 'failed')->count())->toBe(1);
    expect(DeliveryEvent::where('type', 'delivered')->exists())->toBeFalse();
    expect(DeliveryEvent::where('simulated', false)->exists())->toBeFalse();
});
it('rechecks suppression after snapshot and honours cancellation', function () {
    Queue::fake();
    [$u,$o,$c] = preparedCampaign();
    $s = app(CampaignService::class);
    $s->ready($c);
    $s->schedule($c->fresh(), now());
    (new PumpCampaigns)->handle();
    $r = $c->recipients()->first();
    app(EligibilityService::class)->suppress(Contact::find($r->contact_id), 'unsubscribed');
    (new DeliverRecipient($r->id))->handle($s);
    expect($r->fresh()->status)->toBe('suppressed');
    expect(DeliveryAttempt::count())->toBe(0);
    $s->cancel($c->fresh());
    foreach ($c->recipients()->get() as $r) {
        (new DeliverRecipient($r->id))->handle($s);
    }expect(DeliveryAttempt::count())->toBe(0);
});
it('isolates campaign identities templates previews and reporting from other organisations and platform administrators', function () {
    [$u,$o,$c] = preparedCampaign();
    $a = User::factory()->create();
    $a->is_platform_admin = true;
    $a->save();
    $other = workspaceFor($a);
    $this->actingAs($a)->get("/organisations/$o->id/campaigns/$c->id")->assertForbidden();
    $this->get("/organisations/$other->id/campaigns/$c->id/preview")->assertNotFound();
    $this->post("/organisations/$other->id/campaigns/$c->id/ready")->assertNotFound();
    $this->actingAs($u)->get("/organisations/$o->id/reports")->assertOk();
});
it('sanitises unsafe HTML while preserving the unsubscribe placeholder', function () {
    $html = app(EmailContentService::class)->clean('<script>alert(1)</script><img src="javascript:alert(1)" onerror="alert(1)"><a href="{{unsubscribe_url}}">Unsubscribe</a><iframe src="https://evil.test"></iframe>');
    expect($html)->not->toContain('<script', 'onerror', 'javascript:', '<iframe');
    expect($html)->toContain('{{unsubscribe_url}}');
});
it('signs opaque unsubscribe links and applies company scoped idempotent suppression', function () {
    [$u,$o,$campaign] = preparedCampaign();
    $c = Contact::where('organisation_id', $o->id)->first();
    $url = app(UnsubscribeService::class)->url($c);
    expect($url)->not->toContain($c->email);
    $this->get($url)->assertOk();
    expect(app(EligibilityService::class)->eligible($c))->toBeTrue();
    $this->post($url)->assertOk();
    $this->post($url)->assertOk();
    expect(app(EligibilityService::class)->eligible($c))->toBeFalse();
    $this->get($url.'bad')->assertForbidden();
});
it('fails closed if the simulation provider is replaced and does not process suspended companies', function () {
    Queue::fake();
    [$u,$o,$c] = preparedCampaign();
    $s = app(CampaignService::class);
    $s->ready($c);
    $s->schedule($c->fresh(), now());
    $o->status = 'suspended';
    $o->save();
    (new PumpCampaigns)->handle();
    expect($c->fresh()->status)->toBe('scheduled');
    $o->status = 'active';
    $o->save();
    (new PumpCampaigns)->handle();
    config(['dispatch.provider' => 'smtp']);
    $r = $c->recipients()->first();
    try {
        (new DeliverRecipient($r->id))->handle($s);
        $this->fail('External provider must be rejected.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('not authorised');
    }expect(DeliveryAttempt::count())->toBe(0);
});
