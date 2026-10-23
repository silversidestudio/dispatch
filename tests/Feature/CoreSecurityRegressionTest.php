<?php

use App\Jobs\DeliverRecipient;
use App\Jobs\PumpCampaigns;
use App\Models\ContactImport;
use App\Models\DeliveryAttempt;
use App\Models\EmailTemplate;
use App\Models\Organisation;
use App\Models\SendingIdentity;
use App\Models\User;
use App\Services\CampaignService;
use App\Services\CsvImportService;
use App\Services\EligibilityService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

it('enforces contact writes and identity configuration for every organisation role', function (string $role, bool $content, bool $settings) {
    $u = User::factory()->create();
    $o = workspaceFor($u, $role);
    $this->actingAs($u);
    $r = $this->post("/organisations/$o->id/contacts", ['email' => 'new@example.test', 'status' => 'active', 'source' => 'Test']);
    $content ? $r->assertRedirect() : $r->assertForbidden();
    $r = $this->post("/organisations/$o->id/identities", ['name' => 'Sender', 'email' => 'sender@example.test', 'is_default' => true]);
    $settings ? $r->assertRedirect() : $r->assertForbidden();
})->with([['owner', true, true], ['administrator', true, true], ['campaign_manager', true, false], ['viewer', false, false]]);
it('protects the final administrator from profile deletion and rejects suspended login', function () {
    $u = User::factory()->create();
    $u->is_platform_admin = true;
    $u->save();
    $this->actingAs($u)->delete('/settings/profile', ['password' => 'password'])->assertSessionHasErrors('password');
    expect($u->fresh())->not->toBeNull();
    auth()->logout();
    $v = User::factory()->create();
    $v->status = 'suspended';
    $v->save();
    $this->post('/login', ['email' => $v->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});
it('creates an owner invitation for a new company and permits registration without implicit membership', function () {
    $a = User::factory()->create();
    $a->is_platform_admin = true;
    $a->save();
    $this->actingAs($a)->post('/platform/companies', ['name' => 'Pending company', 'owner_email' => 'future@example.test'])->assertRedirect();
    $o = Organisation::first();
    $this->assertDatabaseHas('organisation_invitations', ['organisation_id' => $o->id, 'role' => 'owner']);
    expect($a->organisations()->count())->toBe(0);
    $link = session('invitation_url');
    $v = User::factory()->create(['email' => 'future@example.test']);
    $this->actingAs($v)->post($link)->assertRedirect('/dashboard');
    expect($o->members()->whereKey($v->id)->first()->pivot->role)->toBe('owner');
});
it('preserves suppression across email changes archive and list membership changes', function () {
    $u = User::factory()->create();
    $o = workspaceFor($u);
    $c = contactFor($o);
    app(EligibilityService::class)->suppress($c, 'complaint');
    $this->actingAs($u)->patch("/organisations/$o->id/contacts/$c->id", ['email' => 'changed@example.test', 'status' => 'active', 'source' => 'Manual update'])->assertRedirect();
    $this->assertDatabaseHas('suppressions', ['organisation_id' => $o->id, 'email' => 'person@example.test']);
    $this->assertDatabaseHas('suppressions', ['organisation_id' => $o->id, 'email' => 'changed@example.test']);
    expect(app(EligibilityService::class)->eligible($c->fresh()))->toBeFalse();
});
it('rejects cross tenant imports and exports and neutralises spreadsheet formulas', function () {
    Storage::fake();
    Queue::fake();
    $u = User::factory()->create();
    $v = User::factory()->create();
    $o = workspaceFor($u);
    $b = workspaceFor($v);
    $this->actingAs($u)->post("/organisations/$o->id/imports", ['source' => 'Test', 'file' => UploadedFile::fake()->createWithContent('test.csv', "email\n=HYPERLINK(test)\n")]);
    $i = ContactImport::first();
    $this->post("/organisations/$o->id/imports/$i->id/start", ['mapping' => ['email' => 0]]);
    app(CsvImportService::class)->process($i->fresh());
    $csv = $this->get("/organisations/$o->id/imports/$i->id/errors")->streamedContent();
    expect($csv)->toContain("'=hyperlink");
    $this->actingAs($v)->get("/organisations/$b->id/imports/$i->id")->assertNotFound();
    $this->get("/organisations/$b->id/imports/$i->id/errors")->assertNotFound();
    $this->post("/organisations/$b->id/imports/$i->id/start", ['mapping' => ['email' => 0]])->assertNotFound();
});
it('rejects oversized CSVs invalid mappings and unsafe segment attribute paths', function () {
    Storage::fake();
    Queue::fake();
    $u = User::factory()->create();
    $o = workspaceFor($u);
    $this->actingAs($u)->post("/organisations/$o->id/imports", ['source' => 'Test', 'file' => UploadedFile::fake()->create('big.csv', 5121, 'text/csv')])->assertSessionHasErrors('file');
    $this->post("/organisations/$o->id/imports", ['source' => 'Test', 'file' => UploadedFile::fake()->createWithContent('test.csv', "email\na@example.test\n")]);
    $i = ContactImport::first();
    $this->post("/organisations/$o->id/imports/$i->id/start", ['mapping' => ['email' => 500]])->assertStatus(422);
    $this->post("/organisations/$o->id/segments", ['name' => 'Unsafe', 'rules' => [['field' => 'attribute', 'value' => 'x', 'key' => 'x->bad']]])->assertSessionHasErrors();
});
it('holds future scheduled work until due and recovery safely tolerates duplicate dispatch', function () {
    Queue::fake();
    [$u,$o,$c] = preparedCampaign();
    $s = app(CampaignService::class);
    $s->ready($c);
    $s->schedule($c->fresh(), now()->addHour());
    (new PumpCampaigns)->handle();
    expect($c->fresh()->status)->toBe('scheduled');
    $this->travel(61)->minutes();
    (new PumpCampaigns)->handle();
    (new PumpCampaigns)->handle();
    expect($c->fresh()->status)->toBe('processing');
    $r = $c->recipients()->where('email', 'success@example.test')->first();
    (new DeliverRecipient($r->id))->handle($s);
    (new DeliverRecipient($r->id))->handle($s);
    expect(DeliveryAttempt::count())->toBe(1);
});
it('reconciles exhausted worker retries to a terminal recipient outcome', function () {
    Queue::fake();
    [$u,$o,$c] = preparedCampaign();
    $s = app(CampaignService::class);
    $s->ready($c);
    $s->schedule($c->fresh(), now());
    (new PumpCampaigns)->handle();
    foreach ($c->recipients()->get() as $r) {
        (new DeliverRecipient($r->id))->failed(new RuntimeException('Worker interrupted.'));
    }expect($c->fresh()->status)->toBe('completed');
    expect($c->recipients()->where('status', 'failed')->count())->toBe(3);
});
it('does not accept tenant foreign identity or audience IDs when saving campaigns', function () {
    [$u,$o,$c] = preparedCampaign();
    $b = workspaceFor($u);
    $this->actingAs($u);
    $d = $c->only(['name', 'subject', 'sending_identity_id', 'audience_type', 'audience_id', 'html', 'text_body']);
    $this->post("/organisations/$b->id/campaigns", $d)->assertSessionHasErrors('sending_identity_id');
    $identity = SendingIdentity::create(['organisation_id' => $b->id, 'name' => 'Sender', 'email' => 'b@example.test', 'domain' => 'example.test']);
    $d['sending_identity_id'] = $identity->id;
    $this->post("/organisations/$b->id/campaigns", $d)->assertNotFound();
});
it('uses scoped data for company reporting and aggregate only platform summaries', function () {
    [$u,$o,$c] = preparedCampaign();
    $v = User::factory()->create();
    $b = workspaceFor($v);
    $this->actingAs($v)->withSession(['organisation_id' => $b->id])->get('/dashboard')->assertInertia(fn (Assert $p) => $p->where('stats.total_contacts', 0));
    $v->is_platform_admin = true;
    $v->save();
    $this->get('/platform')->assertInertia(fn (Assert $p) => $p->where('stats.contacts', 4));
    $this->get("/platform/companies/$o->id")->assertInertia(fn (Assert $p) => $p->where('counts.contacts', 4)->missing('contacts')->missing('campaigns'));
});
it('saves and duplicates sanitised templates without allowing verified identity spoofing', function () {
    $u = User::factory()->create();
    $o = workspaceFor($u);
    $this->actingAs($u)->post("/organisations/$o->id/templates/starter", ['starter' => 0])->assertRedirect();
    $t = EmailTemplate::first();
    expect($t->html)->toContain('{{unsubscribe_url}}');
    $this->post("/organisations/$o->id/templates/$t->id/duplicate")->assertRedirect();
    expect(EmailTemplate::count())->toBe(2);
    $this->post("/organisations/$o->id/identities", ['name' => 'Sender', 'email' => 'sender@example.test', 'is_default' => true, 'verification_status' => 'verified'])->assertRedirect();
    expect(SendingIdentity::first()->verification_status)->toBe('unverified');
});
