<?php

use App\Models\AuditLog;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

function workspaceFor(User $u, string $role = 'owner'): Organisation
{
    $o = Organisation::create(['name' => 'Test workspace']);
    $o->members()->attach($u->id, ['role' => $role]);

    return $o;
}
it('requires verification for organisation and platform operations', function () {
    $u = User::factory()->unverified()->create();
    $this->actingAs($u)->get('/organisations')->assertRedirect('/verify-email');
    $this->post('/organisations', ['name' => 'Blocked'])->assertRedirect('/verify-email');
    $this->get('/platform')->assertRedirect('/verify-email');
});
it('onboards verified users and creates an audited owner membership', function () {
    $u = User::factory()->create();
    $this->actingAs($u)->get('/dashboard')->assertRedirect('/organisations');
    $this->post('/organisations', ['name' => 'New workspace', 'is_platform_admin' => true])->assertRedirect('/dashboard');
    $o = Organisation::first();
    expect($o->members()->first()->pivot->role)->toBe('owner');
    expect($u->fresh()->is_platform_admin)->toBeFalse();
    $this->assertDatabaseHas('audit_logs', ['action' => 'organisation.created', 'organisation_id' => $o->id]);
    $this->get('/dashboard')->assertInertia(fn (Assert $p) => $p->component('dashboard')->where('organisation.id', $o->id));
});
it('isolates settings switching and audit activity between organisations', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $oa = workspaceFor($a);
    $ob = workspaceFor($b);
    AuditLog::create(['organisation_id' => $ob->id, 'action' => 'private.other_tenant']);
    $this->actingAs($a)->post("/organisations/{$ob->id}/switch")->assertForbidden();
    $this->get("/organisations/{$ob->id}")->assertForbidden();
    $this->patch("/organisations/{$ob->id}", ['name' => 'Stolen'])->assertForbidden();
    $this->post("/organisations/{$ob->id}/invitations", ['email' => 'x@example.test', 'role' => 'viewer'])->assertForbidden();
    $this->withSession(['organisation_id' => $ob->id])->get('/dashboard')->assertInertia(fn (Assert $p) => $p->where('organisation.id', $oa->id)->has('activity', 0));
});
it('enforces the role matrix', function (string $role, bool $update, bool $members, bool $campaigns) {
    $u = User::factory()->create();
    $o = workspaceFor($u, $role);
    $this->actingAs($u);
    expect(Gate::allows('view', $o))->toBeTrue();
    expect(Gate::allows('update', $o))->toBe($update);
    expect(Gate::allows('manageMembers', $o))->toBe($members);
    expect(Gate::allows('manageCampaigns', $o))->toBe($campaigns);
    $response = $this->patch("/organisations/{$o->id}", ['name' => 'Changed']);
    $update ? $response->assertRedirect() : $response->assertForbidden();
})->with([['owner', true, true, true], ['administrator', true, false, true], ['campaign_manager', false, false, true], ['viewer', false, false, false]]);
it('switches only to active memberships and respects suspension immediately', function () {
    $u = User::factory()->create();
    $a = workspaceFor($u);
    $b = workspaceFor($u, 'viewer');
    $this->actingAs($u)->post("/organisations/{$b->id}/switch")->assertSessionHas('organisation_id', $b->id);
    $b->status = 'suspended';
    $b->save();
    $this->get("/organisations/{$b->id}")->assertForbidden();
    $this->post("/organisations/{$b->id}/switch")->assertForbidden();
    $this->get('/dashboard')->assertInertia(fn (Assert $p) => $p->where('organisation.id', $a->id));
});
it('accepts invitations once only for the verified matching account', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $o = workspaceFor($owner);
    $response = $this->actingAs($owner)->post("/organisations/{$o->id}/invitations", ['email' => $invitee->email, 'role' => 'viewer']);
    $url = session('invitation_url');
    $token = basename($url);
    expect(DB::table('organisation_invitations')->first()->token_hash)->not->toBe($token);
    $this->post('/invitations/'.$token)->assertForbidden();
    $this->actingAs($invitee)->post('/invitations/'.$token)->assertRedirect('/dashboard');
    expect($o->members()->whereKey($invitee->id)->first()->pivot->role)->toBe('viewer');
    $this->post('/invitations/'.$token)->assertForbidden();
});
it('rejects expired invitations and owner role changes', function () {
    $owner = User::factory()->create();
    $i = User::factory()->create();
    $o = workspaceFor($owner);
    $this->actingAs($owner)->post("/organisations/{$o->id}/invitations", ['email' => $i->email, 'role' => 'viewer']);
    $token = basename(session('invitation_url'));
    DB::table('organisation_invitations')->update(['expires_at' => now()->subMinute()]);
    $this->actingAs($i)->post('/invitations/'.$token)->assertForbidden();
    $this->actingAs($owner)->patch("/organisations/{$o->id}/members/{$owner->id}", ['role' => 'viewer'])->assertStatus(422);
});
it('restricts platform and Horizon even in local development', function () {
    $u = User::factory()->create();
    workspaceFor($u);
    $this->actingAs($u)->get('/platform')->assertForbidden();
    $this->get('/horizon')->assertForbidden();
    $u->is_platform_admin = true;
    $u->save();
    $this->get('/platform')->assertOk();
    $this->get('/horizon')->assertOk();
    $outsider = Organisation::create(['name' => 'Unrelated']);
    $this->get("/organisations/{$outsider->id}")->assertForbidden();
    $this->patch("/platform/organisations/{$outsider->id}", ['status' => 'suspended'])->assertRedirect();
    $this->assertDatabaseHas('audit_logs', ['action' => 'organisation.status_updated', 'organisation_id' => $outsider->id]);
});
it('never exposes an external mail transport', function () {
    expect(config('mail.default'))->toBe('log');
    expect(array_keys(config('mail.mailers')))->toBe(['log', 'array']);
});

it('prevents an owner from orphaning a workspace through account deletion', function () {
    $u = User::factory()->create(['password' => bcrypt('test-password')]);
    workspaceFor($u);
    $this->actingAs($u)->delete('/settings/profile', ['password' => 'test-password'])->assertSessionHasErrors('password');
    $this->assertDatabaseHas('users', ['id' => $u->id]);
});
