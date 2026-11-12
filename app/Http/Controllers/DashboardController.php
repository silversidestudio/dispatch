<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\DeliveryEvent;
use App\Models\MailingList;
use App\Models\Suppression;
use App\Services\CampaignAnalytics;
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
            'simulated_acceptances' => DeliveryEvent::where('organisation_id', $o->id)->where('simulated', true)->where('type', 'accepted')->count(), 'emails_delivered' => DeliveryEvent::where('organisation_id', $o->id)->where('provider', 'sendgrid')->where('simulated', false)->where('type', 'delivered')->distinct()->count('campaign_recipient_id'),
        ], 'recentCampaigns' => Campaign::where('organisation_id', $o->id)->latest()->limit(5)->get(['id', 'name', 'status']),
            'providerStatus' => ['provider' => config('dispatch.provider'), 'configured' => (bool) $o->sendgrid_api_key, 'live_enabled' => (bool) config('dispatch.sendgrid.live_enabled')], 'performance' => Campaign::where('organisation_id', $o->id)->whereNotNull('snapshot')->latest()->limit(5)->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'analytics' => app(CampaignAnalytics::class)->summary($c)]), 'alerts' => DeliveryEvent::where('organisation_id', $o->id)->where('simulated', false)->whereIn('type', ['bounce', 'spamreport'])->latest('id')->limit(10)->get(['id', 'campaign_id', 'type', 'occurred_at']), 'canManage' => Gate::allows('update', $o), 'activity' => AuditLog::where('organisation_id', $o->id)->latest('id')->limit(6)->get(['id', 'action', 'created_at'])]);
    }
}
