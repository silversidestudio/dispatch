<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\DeliveryEvent;
use App\Models\MailingList;
use App\Models\Suppression;
use App\Services\EligibilityService;
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

        return Inertia::render('dashboard', ['organisation' => ['id' => $o->id, 'name' => $o->name, 'role' => $o->pivot->role, 'members' => $o->members()->count()], 'stats' => [
            'total_contacts' => Contact::where('organisation_id', $o->id)->count(),
            'eligible_contacts' => app(EligibilityService::class)->query($o->id)->count(),
            'suppressed_addresses' => Suppression::where('organisation_id', $o->id)->count(),
            'mailing_lists' => MailingList::where('organisation_id', $o->id)->count(),
            'draft_campaigns' => Campaign::where('organisation_id', $o->id)->where('status', 'draft')->count(),
            'scheduled_campaigns' => Campaign::where('organisation_id', $o->id)->where('status', 'scheduled')->count(),
            'simulated_acceptances' => DeliveryEvent::where('organisation_id', $o->id)->where('type', 'accepted')->count(),
        ], 'recentCampaigns' => Campaign::where('organisation_id', $o->id)->latest()->limit(5)->get(['id', 'name', 'status']),
            'canManage' => Gate::allows('update', $o), 'activity' => AuditLog::where('organisation_id', $o->id)->latest('id')->limit(6)->get(['id', 'action', 'created_at'])]);
    }
}
