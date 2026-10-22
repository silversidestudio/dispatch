<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Organisation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrganisationController extends Controller
{
    public function index(Request $r)
    {
        return Inertia::render('organisations', ['organisationList' => $r->user()->organisations()->get()->map(fn ($o) => ['id' => $o->id, 'name' => $o->name, 'status' => $o->status, 'role' => $o->pivot->role])]);
    }

    public function store(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:120']);
        $o = DB::transaction(function () use ($r, $data) {
            $o = Organisation::create($data);
            $o->members()->attach($r->user()->id, ['role' => 'owner']);
            AuditLog::record('organisation.created', $o->id);

            return $o;
        });
        $r->session()->put('organisation_id', $o->id);

        return to_route('dashboard')->with('success', 'Organisation created.');
    }

    public function switch(Request $r, Organisation $organisation)
    {
        Gate::authorize('view', $organisation);
        $r->session()->put('organisation_id', $organisation->id);

        return to_route('dashboard')->with('success', 'Organisation switched.');
    }

    public function update(Request $r, Organisation $organisation)
    {
        Gate::authorize('update', $organisation);
        $data = $r->validate(['name' => 'required|string|max:120']);
        DB::transaction(function () use ($organisation, $data) {
            $organisation->update($data);
            AuditLog::record('organisation.updated', $organisation->id);
        });

        return back()->with('success', 'Organisation updated.');
    }

    public function show(Request $r, Organisation $organisation)
    {
        Gate::authorize('view', $organisation);
        $owner = Gate::allows('manageMembers', $organisation);

        return Inertia::render('organisation-settings', ['organisation' => ['id' => $organisation->id, 'name' => $organisation->name], 'canUpdate' => Gate::allows('update', $organisation), 'canManageMembers' => $owner, 'members' => $owner ? $organisation->members()->get()->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->pivot->role]) : [], 'invitations' => $owner ? DB::table('organisation_invitations')->where('organisation_id', $organisation->id)->get(['id', 'email', 'role', 'expires_at']) : []]);
    }

    public function invite(Request $r, Organisation $organisation)
    {
        Gate::authorize('manageMembers', $organisation);
        $data = $r->validate(['email' => 'required|email|max:255', 'role' => 'required|in:administrator,campaign_manager,viewer']);
        $data['email'] = strtolower(trim($data['email']));
        if ($organisation->members()->where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages(['email' => 'This user is already a member.']);
        }
        $token = Str::random(64);
        DB::transaction(function () use ($organisation, $data, $token) {
            DB::table('organisation_invitations')->updateOrInsert(['organisation_id' => $organisation->id, 'email' => $data['email']], ['role' => $data['role'], 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now()]);
            AuditLog::record('invitation.created', $organisation->id);
        });

        return back()->with('success', 'Invitation created. Share the local acceptance link securely.')->with('invitation_url', url('/invitations/'.$token));
    }

    public function accept(Request $r, string $token)
    {
        DB::transaction(function () use ($r, $token) {
            $i = DB::table('organisation_invitations')->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            abort_unless($i && now()->lt($i->expires_at) && strtolower($r->user()->email) === $i->email, 403);
            $o = Organisation::findOrFail($i->organisation_id);
            abort_unless($o->status === 'active', 403);
            $o->members()->syncWithoutDetaching([$r->user()->id => ['role' => $i->role]]);
            DB::table('organisation_invitations')->where('id', $i->id)->delete();
            AuditLog::record('invitation.accepted', $o->id);
            $r->session()->put('organisation_id', $o->id);
        });

        return to_route('dashboard')->with('success', 'Invitation accepted.');
    }

    public function role(Request $r, Organisation $organisation, int $member)
    {
        Gate::authorize('manageMembers', $organisation);
        $data = $r->validate(['role' => 'required|in:administrator,campaign_manager,viewer']);
        DB::transaction(function () use ($organisation, $member, $data) {
            $m = $organisation->members()->where('users.id', $member)->lockForUpdate()->firstOrFail();
            abort_if($m->pivot->role === 'owner', 422, 'Ownership cannot be changed here.');
            $organisation->members()->updateExistingPivot($member, $data);
            AuditLog::record('membership.role_updated', $organisation->id, ['member_id' => $member, 'role' => $data['role']]);
        });

        return back()->with('success', 'Role updated.');
    }
}
