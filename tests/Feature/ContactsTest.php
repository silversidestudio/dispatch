<?php

use App\Jobs\ProcessContactImport;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\EligibilityRecord;
use App\Models\ImportRow;
use App\Models\MailingList;
use App\Models\Segment;
use App\Models\Suppression;
use App\Models\User;
use App\Services\AudienceService;
use App\Services\CsvImportService;
use App\Services\EligibilityService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function contactFor($o, string $email = 'person@example.test'): Contact
{
    return Contact::create(['organisation_id' => $o->id, 'email' => $email, 'status' => 'active', 'source' => 'Test', 'eligibility' => 'unknown']);
}
it('normalises tenant-local emails and prevents duplicate records without global deduplication', function () {
    $u = User::factory()->create();
    $o = workspaceFor($u);
    $b = workspaceFor($u);
    $this->actingAs($u);
    $d = ['email' => 'Person@Example.test', 'source' => 'Manual', 'status' => 'active'];
    $this->post("/organisations/$o->id/contacts", $d)->assertRedirect();
    $this->post("/organisations/$o->id/contacts", $d)->assertSessionHasErrors('email');
    $this->post("/organisations/$b->id/contacts", $d)->assertRedirect();
    expect(Contact::count())->toBe(2);
    expect(Contact::first()->email)->toBe('person@example.test');
});
it('keeps stored contacts ineligible until documented evidence and enforces persistent suppression', function () {
    $u = User::factory()->create();
    $o = workspaceFor($u);
    $c = contactFor($o);
    $s = app(EligibilityService::class);
    expect($s->eligible($c))->toBeFalse();
    $this->actingAs($u)->post("/organisations/$o->id/contacts/$c->id/eligibility", ['basis' => 'consent', 'method' => 'Signup', 'evidence' => 'Explicit email consent recorded on signup', 'recorded_at' => now()->subMinute()->toISOString()])->assertRedirect();
    expect($s->eligible($c))->toBeTrue();
    $s->suppress($c, 'unsubscribed');
    expect($s->eligible($c))->toBeFalse();
    $this->post("/organisations/$o->id/contacts/$c->id/eligibility", ['basis' => 'consent', 'method' => 'Signup', 'evidence' => 'Attempt to overwrite suppression', 'recorded_at' => now()->subMinute()->toISOString()])->assertSessionHasErrors('basis');
});
it('isolates contact views writes lists imports and segments from other tenants and viewers', function () {
    $u = User::factory()->create();
    $v = User::factory()->create();
    $a = workspaceFor($u);
    $b = workspaceFor($v);
    $c = contactFor($b);
    $l = MailingList::create(['organisation_id' => $a->id, 'name' => 'A']);
    $this->actingAs($u)->get("/organisations/$a->id/contacts/$c->id")->assertNotFound();
    $this->get("/organisations/$b->id/contacts")->assertForbidden();
    $this->post("/organisations/$a->id/lists/$l->id/members", ['ids' => [$c->id], 'action' => 'add'])->assertNotFound();
    $a->members()->attach($v->id, ['role' => 'viewer']);
    $this->actingAs($v)->post("/organisations/$a->id/contacts", ['email' => 'new@example.test'])->assertForbidden();
});
it('evaluates structured segments within a tenant and list membership does not restore suppression', function () {
    $u = User::factory()->create();
    $o = workspaceFor($u);
    $c = contactFor($o);
    $l = MailingList::create(['organisation_id' => $o->id, 'name' => 'Test']);
    $l->contacts()->attach($c->id);
    app(EligibilityService::class)->suppress($c, 'complaint');
    $s = Segment::create(['organisation_id' => $o->id, 'name' => 'Source', 'rules' => [['field' => 'source', 'value' => 'Test']]]);
    expect(app(AudienceService::class)->query($o->id, 'segment', $s->id)->count())->toBe(1);
    expect(app(AudienceService::class)->query($o->id, 'list', $l->id, true)->count())->toBe(0);
    $this->actingAs($u)->post("/organisations/$o->id/segments", ['name' => 'Unsafe', 'rules' => [['field' => 'raw_sql', 'value' => '1=1']]])->assertSessionHasErrors();
});
it('imports through a queued mapping workflow with validation duplicates and retry idempotency', function () {
    Storage::fake();
    Queue::fake();
    $u = User::factory()->create();
    $o = workspaceFor($u);
    contactFor($o, 'existing@example.test');
    $this->actingAs($u)->post("/organisations/$o->id/imports", ['source' => 'Upload', 'file' => UploadedFile::fake()->createWithContent('contacts.csv', "email,first_name\nnew@example.test,New\nexisting@example.test,Existing\ninvalid,Bad\n")])->assertRedirect();
    $i = ContactImport::first();
    $this->post("/organisations/$o->id/imports/$i->id/start", ['mapping' => ['email' => 0, 'first_name' => 1]])->assertRedirect();
    Queue::assertPushed(ProcessContactImport::class);
    app(CsvImportService::class)->process($i->fresh());
    $i->refresh();
    expect($i->successful)->toBe(1);
    expect($i->rejected)->toBe(2);
    expect(Contact::count())->toBe(2);
    $new = Contact::where('email', 'new@example.test')->first();
    expect($new->eligibility)->toBe('csv_import');
    expect(app(EligibilityService::class)->eligible($new))->toBeTrue();
    $record = EligibilityRecord::where('contact_id', $new->id)->sole();
    expect($record->user_id)->toBe($u->id);
    expect($record->metadata['import_id'])->toBe($i->id);
    expect($record->basis)->toBe('csv_import');
    expect(app(EligibilityService::class)->eligible(Contact::where('email', 'existing@example.test')->first()))->toBeFalse();
    $i->update(['status' => 'failed']);
    app(CsvImportService::class)->process($i->fresh());
    expect(EligibilityRecord::where('contact_id', $new->id)->count())->toBe(1);
    expect(ImportRow::count())->toBe(3);
    expect(Contact::count())->toBe(2);
});

it('never restores suppressed addresses through CSV import and keeps eligibility tenant scoped', function () {
    Storage::fake();
    $u = User::factory()->create();
    $o = workspaceFor($u);
    $other = workspaceFor($u);
    foreach (['unsubscribed', 'hard_bounce', 'complaint', 'administratively_blocked'] as $reason) {
        Suppression::create(['organisation_id' => $o->id, 'email' => $reason.'@example.test', 'reason' => $reason]);
    }
    $path = 'imports/policy.csv';
    Storage::put($path, "email\nunsubscribed@example.test\nhard_bounce@example.test\ncomplaint@example.test\nadministratively_blocked@example.test\nnew@example.test\n");
    $import = ContactImport::create(['organisation_id' => $o->id, 'user_id' => $u->id, 'path' => $path, 'source' => 'User CSV', 'headers' => ['email'], 'mapping' => ['email' => 0], 'status' => 'queued', 'total' => 5]);
    app(CsvImportService::class)->process($import);
    expect($import->fresh()->successful)->toBe(5);
    expect(app(EligibilityService::class)->query($o->id)->pluck('email')->all())->toBe(['new@example.test']);
    foreach (['unsubscribed', 'hard_bounce', 'complaint', 'administratively_blocked'] as $reason) {
        expect(Contact::where('organisation_id', $o->id)->where('email', $reason.'@example.test')->value('eligibility'))->toBe($reason);
    }
    expect(EligibilityRecord::where('organisation_id', $o->id)->count())->toBe(1);
    $importB = ContactImport::create(['organisation_id' => $other->id, 'user_id' => $u->id, 'path' => $path, 'source' => 'Other company CSV', 'headers' => ['email'], 'mapping' => ['email' => 0], 'status' => 'queued', 'total' => 5]);
    app(CsvImportService::class)->process($importB);
    expect(app(EligibilityService::class)->query($other->id)->count())->toBe(5);
    $new = Contact::where('organisation_id', $o->id)->where('email', 'new@example.test')->firstOrFail();
    app(EligibilityService::class)->suppress($new, 'unsubscribed');
    expect(app(EligibilityService::class)->eligible($new))->toBeFalse();
});
