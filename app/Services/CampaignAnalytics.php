<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\DeliveryAttempt;
use App\Models\DeliveryEvent;

class CampaignAnalytics
{
    public function events(Campaign $c, ?string $from = null, ?string $to = null)
    {
        $q = DeliveryEvent::where('organisation_id', $c->organisation_id)->where('campaign_id', $c->id)->where('provider', 'sendgrid')->where('simulated', false);
        if ($from) {
            $q->where('occurred_at', '>=', $from.' 00:00:00');
        }if ($to) {
            $q->where('occurred_at', '<=', $to.' 23:59:59');
        }

        return $q;
    }

    public function summary(Campaign $c, ?string $from = null, ?string $to = null): array
    {
        $available = ($c->snapshot['delivery_mode'] ?? 'simulation') === 'sendgrid_live';
        $events = $this->events($c, $from, $to)->get();
        $unique = fn ($types) => $events->whereIn('type', (array) $types)->pluck('campaign_recipient_id')->unique()->count();
        $delivered = $unique('delivered');
        $accepted = $events->filter(fn ($e) => in_array($e->type, ['accepted', 'processed', 'delivered', 'bounce', 'blocked', 'dropped', 'spamreport', 'open', 'click', 'deferred']) || (in_array($e->type, ['unsubscribe', 'group_unsubscribe']) && str_starts_with($e->event_key, 'sg-event-')))->pluck('campaign_recipient_id')->unique()->count();
        $opens = $unique('open');
        $clicks = $unique('click');
        $bounce = $unique('bounce');
        $unsub = $unique(['unsubscribe', 'group_unsubscribe']);
        $rate = fn ($n, $d) => $available && $d > 0 ? round(100 * $n / $d, 2) : null;
        $attempts = DeliveryAttempt::where('organisation_id', $c->organisation_id)->where('provider', 'sendgrid')->whereIn('campaign_recipient_id', $c->recipients()->select('id'));
        if ($from) {
            $attempts->where('started_at', '>=', $from.' 00:00:00');
        }if ($to) {
            $attempts->where('started_at', '<=', $to.' 23:59:59');
        }
        $counts = ['original_audience' => $c->audience_count, 'eligible_recipients' => $c->recipients()->count(), 'excluded' => $c->excluded_count, 'suppressed' => $c->snapshot['suppressed_at_snapshot'] ?? 0, 'queued' => $c->recipients()->where('status', 'pending')->count(), 'submitted' => $available ? (clone $attempts)->whereNotIn('outcome', ['not_submitted', 'confirmed_not_submitted', 'cancelled'])->count() : 0, 'accepted' => $accepted, 'processed' => $unique('processed'), 'delivered' => $delivered, 'deferred' => $unique('deferred'), 'bounced' => $bounce, 'blocked' => $unique('blocked'), 'dropped' => $unique('dropped'), 'complaints' => $unique('spamreport'), 'unsubscribes' => $unsub, 'unique_opens' => $opens, 'total_opens' => $events->where('type', 'open')->count(), 'unique_clicks' => $clicks, 'total_clicks' => $events->where('type', 'click')->count(), 'uncertain' => $c->recipients()->where('status', 'uncertain')->count()];
        $links = $events->where('type', 'click')->groupBy('url')->map(fn ($rows, $url) => ['url' => $url, 'total' => $rows->count(), 'unique' => $rows->pluck('campaign_recipient_id')->unique()->count(), 'delivered_percentage' => $rate($rows->pluck('campaign_recipient_id')->unique()->count(), $delivered)])->values();
        $activity = $events->groupBy(fn ($e) => substr((string) $e->occurred_at, 0, 10))->sortKeys()->map(fn ($rows, $day) => ['day' => $day, 'delivered' => $rows->where('type', 'delivered')->pluck('campaign_recipient_id')->unique()->count(), 'opens' => $rows->where('type', 'open')->count(), 'clicks' => $rows->where('type', 'click')->count(), 'bounces' => $rows->where('type', 'bounce')->count(), 'unsubscribes' => $rows->whereIn('type', ['unsubscribe', 'group_unsubscribe'])->pluck('campaign_recipient_id')->unique()->count()])->values();

        return ['available' => $available, 'mode' => $c->snapshot['delivery_mode'] ?? $c->delivery_mode, 'counts' => $counts, 'rates' => ['delivery' => $rate($delivered, $accepted), 'bounce' => $rate($bounce, $accepted), 'unique_open' => ($c->snapshot['open_tracking'] ?? false) ? $rate($opens, $delivered) : null, 'unique_click' => ($c->snapshot['click_tracking'] ?? false) ? $rate($clicks, $delivered) : null, 'click_to_open' => ($c->snapshot['open_tracking'] ?? false) && ($c->snapshot['click_tracking'] ?? false) ? $rate($clicks, $opens) : null, 'unsubscribe' => $rate($unsub, $delivered)], 'links' => $links, 'activity' => $activity];
    }

    public function recipient(CampaignRecipient $r): array
    {
        $events = DeliveryEvent::where('organisation_id', $r->organisation_id)->where('campaign_recipient_id', $r->id)->orderByRaw('COALESCE(occurred_at,created_at) ASC')->orderBy('id')->get();
        $real = $events->where('provider', 'sendgrid')->where('simulated', false);
        $opens = $real->where('type', 'open');
        $clicks = $real->where('type', 'click');

        return ['id' => $r->id, 'email' => $r->email, 'status' => $r->status, 'delivery_status' => $r->delivery_status, 'last_event' => $events->last()?->type, 'last_event_at' => $events->last()?->occurred_at ?? $events->last()?->created_at, 'first_open' => $opens->min('occurred_at'), 'last_open' => $opens->max('occurred_at'), 'open_events' => $opens->count(), 'first_click' => $clicks->min('occurred_at'), 'last_click' => $clicks->max('occurred_at'), 'click_events' => $clicks->count(), 'unsubscribed' => $real->whereIn('type', ['unsubscribe', 'group_unsubscribe'])->isNotEmpty(), 'events' => $events, 'attempts' => DeliveryAttempt::where('organisation_id', $r->organisation_id)->where('campaign_recipient_id', $r->id)->get(['id', 'number', 'outcome', 'http_status', 'diagnostic_code', 'provider_message_id', 'correlation_id', 'started_at', 'finished_at'])];
    }
}
