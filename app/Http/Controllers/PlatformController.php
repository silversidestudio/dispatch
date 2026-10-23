<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\DeliveryAttempt;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PlatformController extends Controller
{
    public function index(Request $r)
    {
        Gate::authorize('platform');
        $search = $r->validate(['search' => 'nullable|string|max:120', 'status' => 'nullable|in:active,suspended'])['search'] ?? '';

        return Inertia::render('platform', ['organisationPage' => Organisation::where('name', 'like', '%'.$search.'%')->when($r->filled('status'), fn ($q) => $q->where('status', $r->input('status')))->withCount('members')->orderBy('name')->paginate(10)->withQueryString(), 'users' => User::select('id', 'name', 'email', 'email_verified_at', 'is_platform_admin')->latest()->limit(20)->get(), 'stats' => ['organisations' => Organisation::count(), 'users' => User::count(), 'active' => Organisation::where('status', 'active')->count(), 'contacts' => Contact::count(), 'campaigns' => Campaign::count(), 'simulated_attempts' => DeliveryAttempt::count()], 'logs' => AuditLog::latest('id')->limit(15)->get(['id', 'action', 'organisation_id', 'created_at']), 'filters' => ['search' => $search, 'status' => $r->input('status', '')], 'runtime' => ['database' => DB::connection()->getDriverName(), 'queue' => config('queue.default'), 'cache' => config('cache.default'), 'provider' => 'Local simulation only']]);
    }

    public function status(Request $r, Organisation $organisation)
    {
        Gate::authorize('platform');
        $data = $r->validate(['status' => 'required|in:active,suspended']);
        DB::transaction(function () use ($organisation, $data) {
            $organisation->status = $data['status'];
            $organisation->save();
            AuditLog::record('organisation.status_updated', $organisation->id, $data);
        });

        return back()->with('success', 'Organisation status updated.');
    }
}
