<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\DeliveryAttempt;
use App\Models\DeliveryEvent;
use App\Models\Organisation;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PlatformCompanyController extends Controller
{
    public function store(Request $r)
    {
        Gate::authorize('platform');
        $d = $r->validate(['name' => 'required|string|max:120', 'owner_email' => 'required|email|max:255', 'website' => 'nullable|url:http,https|max:255', 'postal_address' => 'nullable|string|max:2000']);
        $url = null;
        $o = DB::transaction(function () use ($d, &$url) {
            $o = Organisation::create(collect($d)->except('owner_email')->all());
            $u = User::where('email', strtolower($d['owner_email']))->where('status', 'active')->first();
            if ($u) {
                $o->members()->attach($u->id, ['role' => 'owner']);
            } else {
                $url = app(MembershipService::class)->invite($o, $d['owner_email'], 'owner');
            }
            AuditLog::record('platform.company_created', $o->id);

            return $o;
        });

        return redirect('/platform/companies/'.$o->id)->with('success', 'Company created.')->with('invitation_url', $url);
    }

    public function show(Organisation $organisation)
    {
        Gate::authorize('platform');

        return Inertia::render('platform-company', ['company' => $organisation, 'counts' => ['contacts' => Contact::where('organisation_id', $organisation->id)->count(), 'campaigns' => Campaign::where('organisation_id', $organisation->id)->count(), 'simulated_attempts' => DeliveryAttempt::where('organisation_id', $organisation->id)->count()], 'deliveryActivity' => DeliveryEvent::where('organisation_id', $organisation->id)->selectRaw('type, COUNT(*) as total')->groupBy('type')->get(), 'members' => $organisation->members()->get(['users.id', 'name', 'email', 'status'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'status' => $u->status, 'role' => $u->pivot->role]), 'invites' => DB::table('organisation_invitations')->where('organisation_id', $organisation->id)->get(['id', 'email', 'role', 'expires_at']), 'audit' => AuditLog::where('organisation_id', $organisation->id)->latest('id')->limit(30)->get(), 'userOptions' => User::where('status', 'active')->orderBy('name')->get(['id', 'name', 'email'])]);
    }

    public function update(Request $r, Organisation $organisation)
    {
        Gate::authorize('platform');
        $d = $r->validate(['name' => 'required|string|max:120', 'website' => 'nullable|url:http,https|max:255', 'postal_address' => 'nullable|string|max:2000']);
        DB::transaction(function () use ($organisation, $d) {
            $organisation->update($d);
            AuditLog::record('platform.company_updated', $organisation->id);
        });

        return back()->with('success', 'Company updated.');
    }

    public function member(Request $r, Organisation $organisation)
    {
        Gate::authorize('platform');
        $d = $r->validate(['user_id' => 'required|integer|exists:users,id', 'role' => 'required|in:administrator,campaign_manager,viewer']);
        DB::transaction(function () use ($organisation, $d) {
            Organisation::whereKey($organisation->id)->lockForUpdate()->firstOrFail();
            $u = User::whereKey($d['user_id'])->where('status', 'active')->firstOrFail();
            abort_if($organisation->members()->whereKey($u->id)->exists(), 422, 'Already a member.');
            $organisation->members()->attach($u->id, ['role' => $d['role']]);
            AuditLog::record('platform.member_added', $organisation->id, ['member_id' => $u->id, 'role' => $d['role']]);
        });

        return back()->with('success', 'Member added.');
    }

    public function invite(Request $r, Organisation $organisation)
    {
        Gate::authorize('platform');
        $d = $r->validate(['email' => 'required|email|max:255', 'role' => 'required|in:administrator,campaign_manager,viewer']);
        $url = app(MembershipService::class)->invite($organisation, $d['email'], $d['role']);

        return back()->with('success', 'Invitation ready.')->with('invitation_url', $url);
    }

    public function role(Request $r, Organisation $organisation, int $member)
    {
        Gate::authorize('platform');
        $d = $r->validate(['role' => 'required|in:administrator,campaign_manager,viewer']);
        app(MembershipService::class)->change($organisation, $member, $d['role']);

        return back()->with('success', 'Role updated.');
    }

    public function remove(Organisation $organisation, int $member)
    {
        Gate::authorize('platform');
        app(MembershipService::class)->change($organisation, $member, null);

        return back()->with('success', 'Member removed.');
    }

    public function transfer(Request $r, Organisation $organisation)
    {
        Gate::authorize('platform');
        $d = $r->validate(['member_id' => 'required|integer', 'confirmation' => 'required|in:TRANSFER']);
        app(MembershipService::class)->transfer($organisation, $d['member_id']);

        return back()->with('success', 'Ownership transferred.');
    }

    public function users(Request $r)
    {
        Gate::authorize('platform');
        $d = $r->validate(['search' => 'nullable|string|max:120', 'status' => 'nullable|in:active,suspended']);
        $q = User::query();
        if ($s = $d['search'] ?? null) {
            $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"));
        }if ($s = $d['status'] ?? null) {
            $q->where('status', $s);
        }

        return Inertia::render('platform-users', ['accounts' => $q->with('organisations:id,name')->orderBy('name')->paginate(15)->withQueryString(), 'filters' => $d, 'invitations' => DB::table('organisation_invitations')->latest('id')->limit(30)->get(['id', 'organisation_id', 'email', 'role', 'expires_at']), 'audit' => AuditLog::whereIn('action', ['platform.account_status_changed', 'platform.admin_promoted', 'platform.initial_admin_created'])->latest('id')->limit(30)->get()]);
    }

    public function userStatus(Request $r, User $user)
    {
        Gate::authorize('platform');
        $d = $r->validate(['status' => 'required|in:active,suspended']);
        DB::transaction(function () use ($user, $d) {
            $admins = User::where('is_platform_admin', true)->orderBy('id')->lockForUpdate()->get();
            $target = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($target->is_platform_admin && $d['status'] === 'suspended') {
                abort_if($admins->where('status', 'active')->count() <= 1, 422, 'The final active platform administrator cannot be suspended.');
            }$target->status = $d['status'];
            $target->save();
            if ($d['status'] === 'suspended') {
                DB::table('sessions')->where('user_id', $target->id)->delete();
            }AuditLog::record('platform.account_status_changed', null, ['account_id' => $target->id, 'status' => $d['status']]);
        });

        return back()->with('success', 'Account status updated.');
    }
}
