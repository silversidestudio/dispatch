<?php

use App\Models\Organisation;
use App\Models\User;

it('creates companies for an existing owner without implicit platform membership', function () {
    $admin = User::factory()->create();
    $admin->is_platform_admin = true;
    $admin->save();
    $owner = User::factory()->create();
    $this->actingAs($admin)->post('/platform/companies', ['name' => 'Company', 'owner_email' => $owner->email])->assertRedirect();
    $o = Organisation::first();
    expect($o->members()->whereKey($owner->id)->first()->pivot->role)->toBe('owner');
    $this->get('/organisations/'.$o->id)->assertForbidden();
    $this->get('/platform/companies/'.$o->id)->assertOk();
    $this->assertDatabaseHas('audit_logs', ['action' => 'platform.company_created']);
});
it('protects the last owner and transfers ownership explicitly', function () {
    $u = User::factory()->create();
    $v = User::factory()->create();
    $o = workspaceFor($u);
    $o->members()->attach($v->id, ['role' => 'viewer']);
    $this->actingAs($u)->delete("/organisations/$o->id/members/$u->id")->assertSessionHasErrors('member');
    $this->post("/organisations/$o->id/transfer", ['member_id' => $v->id, 'confirmation' => 'TRANSFER'])->assertRedirect();
    expect($o->members()->whereKey($v->id)->first()->pivot->role)->toBe('owner');
    expect($o->members()->whereKey($u->id)->first()->pivot->role)->toBe('administrator');
});
it('protects the final active platform admin and invalidates suspended access', function () {
    $a = User::factory()->create();
    $a->is_platform_admin = true;
    $a->save();
    $this->actingAs($a)->patch("/platform/users/$a->id/status", ['status' => 'suspended'])->assertStatus(422);
    $u = User::factory()->create();
    $this->patch("/platform/users/$u->id/status", ['status' => 'suspended'])->assertRedirect();
    $this->actingAs($u->fresh())->get('/dashboard')->assertForbidden();

});
it('rejects ordinary users in platform controls', function () {
    $u = User::factory()->create();
    $this->actingAs($u)->post('/platform/companies', ['name' => 'No', 'owner_email' => $u->email])->assertForbidden();
    $this->get('/platform/users')->assertForbidden();
});
