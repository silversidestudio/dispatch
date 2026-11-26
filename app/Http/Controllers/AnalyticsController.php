<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Organisation;
use App\Services\CampaignAnalytics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    private function check(Organisation $o, Campaign $c): void
    {
        Gate::authorize('view', $o);
        abort_unless($c->organisation_id === $o->id, 404);
        abort_unless(auth()->user()->is_platform_admin || $c->delivery_mode === 'sendgrid_live', 404);
    }

    private function filterEventType(Builder $events, string $type): void
    {
        $events->whereIn('type', $type === 'unsubscribe' ? ['unsubscribe', 'group_unsubscribe'] : [$type]);
    }

    private function filters(Request $r): array
    {
        return $r->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d'.($r->filled('from') ? '|after_or_equal:from' : ''), 'search' => 'nullable|string|max:120', 'status' => 'nullable|in:delivered,deferred,bounce,blocked,dropped,open,click,unsubscribe,spamreport', 'recent_events_per_page' => ['sometimes', 'in:10,50,100']]);
    }

    public function show(Request $r, Organisation $organisation, Campaign $campaign, CampaignAnalytics $s)
    {
        $this->check($organisation, $campaign);
        $d = $this->filters($r);
        $q = $campaign->recipients()->where('organisation_id', $organisation->id);
        if ($v = $d['search'] ?? null) {
            $q->where('email', 'like', "%$v%");
        }
        if (($d['from'] ?? null) || ($d['to'] ?? null) || ($d['status'] ?? null)) {
            $matches = $s->events($campaign, $d['from'] ?? null, $d['to'] ?? null);
            if ($type = $d['status'] ?? null) {
                $this->filterEventType($matches, $type);
            }
            $q->whereIn('id', $matches->select('campaign_recipient_id'));
        }
        $recipients = $q->paginate(20)->withQueryString();
        $recipients->through(fn ($r) => collect($s->recipient($r))->except(['events', 'attempts'])->all());
        $recentEventsPerPage = (int) ($d['recent_events_per_page'] ?? 10);
        $recentEvents = $s->events($campaign, $d['from'] ?? null, $d['to'] ?? null)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($recentEventsPerPage, ['*'], 'recent_events_page')
            ->withQueryString();

        return Inertia::render('campaign-analytics', ['company' => $organisation, 'campaign' => $campaign->only(['id', 'name', 'status']), 'analytics' => $s->summary($campaign, $d['from'] ?? null, $d['to'] ?? null), 'recipients' => $recipients, 'filters' => $d, 'recentEvents' => $recentEvents, 'recentEventsPerPage' => $recentEventsPerPage]);
    }

    public function recipient(Organisation $organisation, Campaign $campaign, CampaignRecipient $recipient, CampaignAnalytics $s)
    {
        $this->check($organisation, $campaign);
        abort_unless($recipient->organisation_id === $organisation->id && $recipient->campaign_id === $campaign->id, 404);

        return Inertia::render('recipient-activity', ['company' => $organisation, 'campaign' => $campaign->only(['id', 'name']), 'recipient' => $s->recipient($recipient), 'canReconcile' => Gate::allows('update', $organisation)]);
    }

    public function export(Request $r, Organisation $organisation, Campaign $campaign, CampaignAnalytics $s)
    {
        $this->check($organisation, $campaign);
        $d = $this->filters($r);
        $q = $s->events($campaign, $d['from'] ?? null, $d['to'] ?? null)->orderBy('id');
        if ($d['status'] ?? null) {
            $this->filterEventType($q, $d['status']);
        }
        if ($d['search'] ?? null) {
            $q->whereIn('campaign_recipient_id', $campaign->recipients()->where('organisation_id', $organisation->id)->where('email', 'like', '%'.$d['search'].'%')->select('id'));
        }

        return response()->streamDownload(function () use ($q) {
            $h = fopen('php://output', 'w');
            fputcsv($h, ['recipient_id', 'event', 'event_time_utc', 'provider_message_id', 'safe_destination_origin']);
            $safe = fn ($v) => preg_match('/^[\s]*[=+@\-\t\r]/', (string) $v) ? "'".$v : (string) $v;
            $q->chunkById(200, function ($rows) use ($h, $safe) {
                foreach ($rows as $e) {
                    fputcsv($h, array_map($safe, [$e->campaign_recipient_id, $e->type, $e->occurred_at, $e->provider_message_id, $e->url]));
                }
            });
            fclose($h);
        }, 'campaign-events.csv', ['Content-Type' => 'text/csv', 'Cache-Control' => 'private, no-store']);
    }

    public function comparison(Request $r, Organisation $organisation, CampaignAnalytics $s)
    {
        Gate::authorize('view', $organisation);
        $canUseSimulation = (bool) $r->user()->is_platform_admin;
        $d = $this->filters($r);
        $q = Campaign::where('organisation_id', $organisation->id)->whereNotNull('snapshot')->when(! $canUseSimulation, fn ($query) => $query->where('delivery_mode', 'sendgrid_live'));
        if ($d['from'] ?? null) {
            $q->whereDate('scheduled_at', '>=', $d['from']);
        }if ($d['to'] ?? null) {
            $q->whereDate('scheduled_at', '<=', $d['to']);
        }$campaigns = $q->latest('scheduled_at')->paginate(15)->withQueryString();
        $campaigns->through(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'scheduled_at' => $c->scheduled_at, 'status' => $c->status, 'analytics' => $s->summary($c)]);

        return Inertia::render('campaign-comparison', ['company' => $organisation, 'campaigns' => $campaigns, 'filters' => $d, 'canUseSimulation' => $canUseSimulation]);
    }
}
