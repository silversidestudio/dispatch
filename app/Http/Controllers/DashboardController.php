<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $r)
    {
        $o = $r->user()->organisations()->where('status', 'active')->whereKey($r->session()->get('organisation_id'))->first();
        if (! $o) {
            $o = $r->user()->organisations()->where('status', 'active')->first();
            if ($o) {
                $r->session()->put('organisation_id', $o->id);
            }
        }
        if (! $o) {
            return to_route('organisations.index');
        }
        Gate::authorize('view', $o);

        return Inertia::render('dashboard', ['organisation' => ['id' => $o->id, 'name' => $o->name, 'role' => $o->pivot->role, 'members' => $o->members()->count()], 'canManage' => Gate::allows('update', $o), 'activity' => AuditLog::where('organisation_id', $o->id)->latest('id')->limit(6)->get(['id', 'action', 'created_at'])]);
    }
}
