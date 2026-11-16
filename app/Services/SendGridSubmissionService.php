<?php

namespace App\Services;

use App\Delivery\DeliveryMessage;
use App\Delivery\SendGridDeliveryProvider;
use App\Delivery\SubmissionResult;
use App\Exceptions\PilotSubmissionBlocked;
use App\Jobs\DeliverRecipient;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\DeliveryAttempt;
use App\Models\DeliveryEvent;
use App\Models\Organisation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class SendGridSubmissionService
{
    public function handle(int $id): void
    {
        $initial = CampaignRecipient::findOrFail($id);
        $attempt = DB::transaction(function () use ($initial) {
            $c = Campaign::whereKey($initial->campaign_id)->lockForUpdate()->firstOrFail();
            $r = CampaignRecipient::whereKey($initial->id)->lockForUpdate()->firstOrFail();
            if ($r->organisation_id !== $c->organisation_id || $c->status !== 'processing' || ! in_array($r->status, ['pending', 'deferred']) || ($r->next_attempt_at && $r->next_attempt_at->isFuture())) {
                return null;
            }
            if (Organisation::whereKey($c->organisation_id)->value('status') !== 'active') {
                return null;
            }
            $contact = Contact::where('organisation_id', $c->organisation_id)->lockForUpdate()->find($r->contact_id);
            if (! $contact || $contact->email !== $r->email || ! app(EligibilityService::class)->eligible($contact)) {
                $r->update(['status' => 'suppressed']);
                app(CampaignService::class)->event($r, 'suppressed', 'suppressed-'.$r->id);

                return null;
            }
            $pilot = app(SendGridPilotGuard::class);
            $block = ($c->snapshot['delivery_mode'] ?? '') === 'sendgrid_live' ? $pilot->blockReason($r->email, $c->organisation_id) : null;
            if ($block) {
                $c->update(['status' => 'paused', 'paused_from' => 'processing']);
                AuditLog::record('campaign.pilot_blocked', $c->organisation_id, ['campaign_id' => $c->id, 'diagnostic' => $block]);

                return null;
            }
            if (app(ProviderReadiness::class)->errors($c, true)) {
                $r->update(['status' => 'deferred', 'next_attempt_at' => now()->addMinute()]);

                return null;
            }
            $rateKey = 'sendgrid-submissions:'.$c->organisation_id;
            $allowed = Cache::lock($rateKey.':reservation', 5)->block(2, function () use ($rateKey) {
                if (RateLimiter::tooManyAttempts($rateKey, config('dispatch.sendgrid.per_minute'))) {
                    return false;
                }RateLimiter::hit($rateKey, 60);

                return true;
            });
            if (! $allowed) {
                $r->update(['status' => 'deferred', 'next_attempt_at' => now()->addSeconds(max(5, RateLimiter::availableIn($rateKey)))]);

                return null;
            }
            $number = $r->attempt_count + 1;
            $a = DeliveryAttempt::create(['organisation_id' => $r->organisation_id, 'campaign_recipient_id' => $r->id, 'number' => $number, 'provider' => 'sendgrid', 'correlation_id' => (string) Str::uuid(), 'outcome' => 'submitting', 'provider_message_id' => '', 'started_at' => now()]);
            $r->update(['status' => 'submitting', 'attempt_count' => $number, 'next_attempt_at' => null]);

            return $a;
        });
        if (! $attempt) {
            app(CampaignService::class)->complete($initial->campaign_id);

            return;
        }
        // No external request is made inside a database transaction. A reserved attempt is never replayed.
        $r = $initial->fresh();
        $c = Campaign::findOrFail($r->campaign_id);
        $contact = Contact::where('organisation_id', $r->organisation_id)->find($r->contact_id);
        if ($c->status !== 'processing' || Organisation::whereKey($c->organisation_id)->value('status') !== 'active' || ! $contact || $contact->email !== $r->email || ! app(EligibilityService::class)->eligible($contact) || app(ProviderReadiness::class)->errors($c, true)) {
            $this->finish($attempt, new SubmissionResult('not_submitted', '', null, 'pre_submission_guard'));

            return;
        }
        $unsubscribe = app(UnsubscribeService::class);
        $values = [...$r->personalisation, 'sender_name' => $c->snapshot['sender_name'], 'sender_address' => $c->snapshot['sender_address'], 'unsubscribe_url' => $c->snapshot['unsubscribe_base'].$unsubscribe->url($contact, $r)];
        $content = app(EmailContentService::class);
        $oneClick = $c->snapshot['unsubscribe_base'].$unsubscribe->oneClickUrl($contact, $r);
        $plain = $content->personaliseText($c->snapshot['text_body'], $values);
        if (! str_contains($c->snapshot['text_body'], '{{sender_address}}')) {
            $plain .= "\n\n".$c->snapshot['sender_name']."\n".$c->snapshot['sender_address'];
        }
        $message = new DeliveryMessage($attempt->correlation_id, $r->email, $c->snapshot['sender_email'], $c->snapshot['subject'], $content->render($c->snapshot['html'], $values, $c->snapshot['preview_text'] ?? ''), $plain, $c->snapshot['reply_to'] ?? null, $c->snapshot['delivery_mode'] === 'sendgrid_sandbox', (bool) $c->live_authorised_at, (bool) $c->snapshot['open_tracking'], (bool) $c->snapshot['click_tracking'], ['List-Unsubscribe' => '<'.$oneClick.'>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'], $c->snapshot['sender_name'], $c->organisation_id, $c->snapshot['sendgrid_credential'] ?? null);
        try {
            $result = app(SendGridDeliveryProvider::class)->submit($message, $attempt->number);
        } catch (PilotSubmissionBlocked $e) {
            $result = new SubmissionResult('not_submitted', '', null, $e->getMessage());
        } catch (\Throwable) {
            $result = new SubmissionResult('uncertain', '', null, 'submission_interrupted');
        }
        $this->finish($attempt, $result);
    }

    public function finish(DeliveryAttempt $attempt, SubmissionResult $result): void
    {
        $campaignId = DB::transaction(function () use ($attempt, $result) {
            $initial = CampaignRecipient::findOrFail($attempt->campaign_recipient_id);
            $c = Campaign::whereKey($initial->campaign_id)->lockForUpdate()->firstOrFail();
            $r = CampaignRecipient::whereKey($initial->id)->lockForUpdate()->firstOrFail();
            $a = DeliveryAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            // A signed webhook can establish acceptance before the HTTP acknowledgement is persisted.
            if ($a->outcome === 'accepted') {
                if ($result->httpStatus) {
                    $a->update(['http_status' => $result->httpStatus, 'finished_at' => now()]);
                }

                return $c->id;
            }
            if (! in_array($a->outcome, ['submitting', 'uncertain'])) {
                return $c->id;
            }
            $outcome = $result->outcome;
            $a->update(['outcome' => $outcome, 'provider_message_id' => $result->messageId, 'http_status' => $result->httpStatus, 'diagnostic_code' => $result->diagnostic, 'finished_at' => now()]);
            $retryableHold = $outcome === 'not_submitted' && (in_array($result->diagnostic, ['pilot_scope_blocked', 'pilot_daily_limit_reached'], true)
                || ($c->status === 'paused' && $result->diagnostic === 'pre_submission_guard'));
            $status = $retryableHold && $c->status !== 'cancelled' ? 'pending' : match ($outcome) {
                'accepted' => 'accepted','sandbox_validated' => 'sandbox_validated','uncertain' => 'uncertain','deferred' => 'deferred','not_submitted' => 'cancelled',default => 'failed'
            };
            if ($status === 'deferred' && $a->number >= config('dispatch.sendgrid.max_attempts')) {
                $status = 'failed';
            }
            $next = $status === 'deferred' ? now()->addSeconds(max($result->retryAfter ?? 0, min(900, 5 * (2 ** min(7, $a->number))) + random_int(0, 10))) : null;
            $r->update(['status' => $status, 'provider_message_id' => $result->messageId, 'next_attempt_at' => $next]);
            DeliveryEvent::firstOrCreate(['event_key' => 'sendgrid-attempt-'.$a->id], ['organisation_id' => $r->organisation_id, 'campaign_id' => $r->campaign_id, 'campaign_recipient_id' => $r->id, 'delivery_attempt_id' => $a->id, 'type' => $outcome, 'provider' => 'sendgrid', 'simulated' => $c->snapshot['delivery_mode'] !== 'sendgrid_live', 'occurred_at' => now(), 'provider_message_id' => $result->messageId, 'metadata' => ['http_status' => $result->httpStatus, 'diagnostic' => $result->diagnostic]]);
            if ($result->diagnostic === 'provider_authentication_failed' || str_starts_with($result->diagnostic ?? '', 'pilot_')) {
                if ($c->status === 'processing') {
                    $c->update(['status' => 'paused', 'paused_from' => 'processing']);
                }
                AuditLog::record($result->diagnostic === 'provider_authentication_failed' ? 'campaign.provider_authentication_failed' : 'campaign.pilot_blocked', $c->organisation_id, ['campaign_id' => $c->id, 'diagnostic' => $result->diagnostic]);
            }
            if ($next) {
                DeliverRecipient::dispatch($r->id)->delay($next)->afterCommit();
            }

            return $c->id;
        });
        app(CampaignService::class)->complete($campaignId);
    }

    public function reconcile(DeliveryAttempt $a, string $decision, string $evidence): void
    {
        DB::transaction(function () use ($a, $decision, $evidence) {
            $initial = CampaignRecipient::findOrFail($a->campaign_recipient_id);
            $c = Campaign::whereKey($initial->campaign_id)->lockForUpdate()->firstOrFail();
            $r = CampaignRecipient::whereKey($initial->id)->lockForUpdate()->firstOrFail();
            $a = DeliveryAttempt::whereKey($a->id)->lockForUpdate()->firstOrFail();
            abort_unless($a->outcome === 'uncertain', 422);
            if ($decision === 'confirmed_not_submitted') {
                abort_if(DeliveryEvent::where('delivery_attempt_id', $a->id)->where('event_key', 'like', 'sg-event-%')->exists(), 422, 'Provider evidence already exists.');
                abort_unless(in_array($c->status, ['processing', 'paused']), 422);
                $r->update(['status' => 'pending', 'next_attempt_at' => null]);
            } else {
                $r->update(['status' => 'failed']);
            }
            $a->update(['outcome' => $decision, 'diagnostic_code' => 'manually_reconciled']);
            AuditLog::record('submission.reconciled', $c->organisation_id, ['attempt_id' => $a->id, 'decision' => $decision, 'evidence' => $evidence]);
        });
    }
}
