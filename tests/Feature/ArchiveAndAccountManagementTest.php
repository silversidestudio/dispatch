<?php

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\DeliveryEvent;
use App\Models\MailingList;
use App\Models\Organisation;
use App\Models\SendingIdentity;
use App\Models\User;
use App\Services\AudienceService;
use App\Services\EligibilityService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function archiveTestWorkspace(User $user): Organisation
{
    $organisation = Organisation::create(['name' => 'Archive Test Workspace']);
    $organisation->members()->attach($user->id, ['role' => 'owner']);

    return $organisation;
}

function archiveTestContact($organisation, string $email): Contact
{
    return Contact::create(['organisation_id' => $organisation->id, 'email' => $email, 'status' => 'active', 'source' => 'Test', 'eligibility' => 'unknown']);
}

it('archives and restores contacts without changing eligibility or suppression history', function () {
    $user = User::factory()->create();
    $organisation = archiveTestWorkspace($user);
    $contact = archiveTestContact($organisation, 'retained@example.test');
    app(EligibilityService::class)->suppress($contact, 'unsubscribed', 'Customer request');
    $this->actingAs($user);

    $this->post("/organisations/$organisation->id/contacts/bulk", ['ids' => [$contact->id], 'action' => 'archive', 'view' => 'active'])->assertRedirect();
    $this->get("/organisations/$organisation->id/contacts?view=archived")->assertOk();
    $this->post("/organisations/$organisation->id/contacts/bulk", ['ids' => [$contact->id], 'action' => 'delete', 'view' => 'archived'])->assertRedirect();
    $this->get("/organisations/$organisation->id/contacts?view=trash")->assertOk();
    $this->post("/organisations/$organisation->id/contacts/bulk", ['ids' => [$contact->id], 'action' => 'restore', 'view' => 'trash'])->assertRedirect();

    expect(Contact::find($contact->id)->status)->toBe('archived');
    expect(Contact::find($contact->id)->eligibility)->toBe('unsubscribed');
    $this->assertDatabaseHas('suppressions', ['organisation_id' => $organisation->id, 'email' => 'retained@example.test', 'reason' => 'unsubscribed']);
});

it('allows list archive and restore but protects lists used by campaigns', function () {
    $user = User::factory()->create();
    $organisation = archiveTestWorkspace($user);
    $list = MailingList::create(['organisation_id' => $organisation->id, 'name' => 'Old list']);
    $this->actingAs($user);

    $this->post("/organisations/$organisation->id/lists/$list->id/archive")->assertRedirect();
    $this->get("/organisations/$organisation->id/audiences?view=archived")->assertOk();
    expect(fn () => app(AudienceService::class)->query($organisation->id, 'list', $list->id)->count())->toThrow(ModelNotFoundException::class);
    $this->post("/organisations/$organisation->id/lists/$list->id/restore")->assertRedirect();
    expect($list->fresh()->archived_at)->toBeNull();

    $identity = SendingIdentity::create(['organisation_id' => $organisation->id, 'name' => 'Sender', 'email' => 'sender@example.test', 'domain' => 'example.test']);
    Campaign::create(['organisation_id' => $organisation->id, 'created_by' => $user->id, 'sending_identity_id' => $identity->id, 'name' => 'Draft', 'subject' => 'Subject', 'html' => 'Body', 'text_body' => 'Body', 'audience_type' => 'list', 'audience_id' => $list->id, 'status' => 'draft']);
    $this->delete("/organisations/$organisation->id/lists/$list->id")->assertStatus(422);
});

it('moves only unsent drafts to recently deleted and preserves sent campaign records', function () {
    $user = User::factory()->create();
    $organisation = archiveTestWorkspace($user);
    $identity = SendingIdentity::create(['organisation_id' => $organisation->id, 'name' => 'Sender', 'email' => 'sender@example.test', 'domain' => 'example.test']);
    $draft = Campaign::create(['organisation_id' => $organisation->id, 'created_by' => $user->id, 'sending_identity_id' => $identity->id, 'name' => 'Draft', 'subject' => 'Subject', 'html' => 'Body', 'text_body' => 'Body', 'audience_type' => 'list', 'audience_id' => 999, 'status' => 'draft']);
    $sent = Campaign::create(['organisation_id' => $organisation->id, 'created_by' => $user->id, 'sending_identity_id' => $identity->id, 'name' => 'Sent', 'subject' => 'Subject', 'html' => 'Body', 'text_body' => 'Body', 'audience_type' => 'list', 'audience_id' => 999, 'status' => 'completed', 'snapshot' => ['delivery_mode' => 'simulation']]);
    $this->actingAs($user);

    $this->delete("/organisations/$organisation->id/campaigns/$draft->id")->assertRedirect();
    $this->get("/organisations/$organisation->id/campaigns?view=trash")->assertOk();
    $this->post("/organisations/$organisation->id/campaigns/$draft->id/restore")->assertRedirect();
    $this->delete("/organisations/$organisation->id/campaigns/$sent->id")->assertStatus(422);
    $this->post("/organisations/$organisation->id/campaigns/$sent->id/archive")->assertRedirect();
    expect($sent->fresh()->snapshot['delivery_mode'])->toBe('simulation');
    $this->post("/organisations/$organisation->id/campaigns/$sent->id/restore")->assertRedirect();
    expect($sent->fresh()->status)->toBe('completed');
    expect($sent->fresh()->archived_at)->toBeNull();
});

it('lets platform administrators edit account details and re-verifies changed addresses', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $user = User::factory()->create();
    $this->actingAs($admin)->patch("/platform/users/$user->id", ['name' => 'Updated Name', 'email' => 'new-address@example.test'])->assertRedirect();

    expect($user->fresh()->name)->toBe('Updated Name');
    expect($user->fresh()->email_verified_at)->toBeNull();
    Notification::assertSentTo($user->fresh(), VerifyEmail::class);
    $this->assertDatabaseHas('audit_logs', ['action' => 'platform.account_updated']);
});

it('keeps tidy-up and restore actions inside the company and role permissions', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $first = archiveTestWorkspace($owner);
    $second = archiveTestWorkspace($viewer);
    $contact = archiveTestContact($first, 'private@example.test');
    $contact->delete();

    $this->actingAs($viewer)
        ->post("/organisations/$first->id/contacts/bulk", ['ids' => [$contact->id], 'action' => 'restore', 'view' => 'trash'])
        ->assertForbidden();
    $this->actingAs($owner)
        ->post("/organisations/$second->id/contacts/bulk", ['ids' => [$contact->id], 'action' => 'restore', 'view' => 'trash'])
        ->assertForbidden();
});

it('paginates provider events and campaign state history independently', function () {
    $user = User::factory()->create();
    $organisation = archiveTestWorkspace($user);
    $identity = SendingIdentity::create(['organisation_id' => $organisation->id, 'name' => 'Sender', 'email' => 'sender@example.test', 'domain' => 'example.test']);
    $list = MailingList::create(['organisation_id' => $organisation->id, 'name' => 'Audience']);
    $contact = archiveTestContact($organisation, 'recipient@example.test');
    $campaign = Campaign::create(['organisation_id' => $organisation->id, 'created_by' => $user->id, 'sending_identity_id' => $identity->id, 'name' => 'Long campaign', 'subject' => 'Subject', 'html' => 'Body', 'text_body' => 'Body', 'audience_type' => 'list', 'audience_id' => $list->id, 'status' => 'completed']);
    $recipient = CampaignRecipient::create(['organisation_id' => $organisation->id, 'campaign_id' => $campaign->id, 'contact_id' => $contact->id, 'email' => $contact->email, 'personalisation' => [], 'status' => 'accepted']);
    foreach (range(1, 26) as $index) {
        DeliveryEvent::create(['organisation_id' => $organisation->id, 'campaign_id' => $campaign->id, 'campaign_recipient_id' => $recipient->id, 'type' => 'submitted', 'event_key' => "event-$index"]);
        AuditLog::create(['organisation_id' => $organisation->id, 'user_id' => $user->id, 'action' => 'campaign.updated', 'metadata' => ['campaign_id' => $campaign->id], 'created_at' => now()->addSeconds($index)]);
    }

    $this->actingAs($user)->get("/organisations/$organisation->id/campaigns/$campaign->id?events_page=2&history_page=2")
        ->assertInertia(fn (Assert $page) => $page
            ->where('events.total', 26)
            ->where('events.current_page', 2)
            ->has('events.data', 1)
            ->where('history.total', 26)
            ->where('history.current_page', 2)
            ->has('history.data', 1));
});

it('passes the selected contact view to the page for its selected styling and section heading', function () {
    $user = User::factory()->create();
    $organisation = archiveTestWorkspace($user);

    $this->actingAs($user)->get("/organisations/$organisation->id/contacts?view=archived")
        ->assertInertia(fn (Assert $page) => $page->where('view', 'archived'));
});
