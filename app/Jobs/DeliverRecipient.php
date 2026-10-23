<?php

namespace App\Jobs;

use App\Delivery\DeliveryMessage;
use App\Delivery\SimulatedDeliveryProvider;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\DeliveryAttempt;
use App\Models\Organisation;
use App\Services\CampaignService;
use App\Services\EligibilityService;
use App\Services\EmailContentService;
use App\Services\UnsubscribeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class DeliverRecipient implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $recipientId) {}

    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function handle(CampaignService $service): void
    {
        $initial = CampaignRecipient::findOrFail($this->recipientId);
        DB::transaction(function () use ($initial, $service) {
            $c = Campaign::whereKey($initial->campaign_id)->lockForUpdate()->firstOrFail();
            $r = CampaignRecipient::whereKey($initial->id)->lockForUpdate()->firstOrFail();
            if ($c->status !== 'processing' || ! in_array($r->status, ['pending', 'deferred']) || ($r->next_attempt_at && $r->next_attempt_at->isFuture())) {
                return;
            }
            if (Organisation::whereKey($c->organisation_id)->value('status') !== 'active') {
                return;
            }
            $contact = Contact::where('organisation_id', $c->organisation_id)->whereKey($r->contact_id)->lockForUpdate()->first();
            if (! $contact || $contact->email !== $r->email || ! app(EligibilityService::class)->eligible($contact)) {
                $r->update(['status' => 'suppressed']);
                $service->event($r, 'suppressed', 'suppressed-'.$r->id);

                return;
            }
            if (config('dispatch.provider') !== 'simulation' || ($c->snapshot['provider'] ?? null) !== 'simulation') {
                throw new \RuntimeException('External delivery is not authorised.');
            }
            $number = $r->attempt_count + 1;
            $values = [...$r->personalisation, 'sender_name' => $c->snapshot['sender_name'], 'sender_address' => $c->snapshot['sender_address'], 'unsubscribe_url' => $c->snapshot['unsubscribe_base'].app(UnsubscribeService::class)->url($contact)];
            $content = app(EmailContentService::class);
            $result = app(SimulatedDeliveryProvider::class)->submit(new DeliveryMessage('recipient-'.$r->id, $r->email, $c->snapshot['sender_email'], $c->snapshot['subject'], $content->render($c->snapshot['html'], $values), $content->personaliseText($c->snapshot['text_body'], $values), $c->snapshot['reply_to'] ?? null), $number);
            $service->event($r, 'submitted', 'submitted-'.$r->id.'-'.$number, ['attempt' => $number]);
            DeliveryAttempt::firstOrCreate(['campaign_recipient_id' => $r->id, 'number' => $number], ['organisation_id' => $r->organisation_id, 'outcome' => $result->outcome, 'provider_message_id' => $result->messageId]);
            $r->attempt_count = $number;
            $r->provider_message_id = $result->messageId;
            $r->status = $result->outcome === 'deferred' ? 'deferred' : ($result->outcome === 'accepted' ? 'accepted' : 'failed');
            $r->next_attempt_at = $result->outcome === 'deferred' ? now()->addSeconds(config('dispatch.retry_seconds')) : null;
            $r->save();
            $service->event($r, $result->outcome, 'attempt-'.$r->id.'-'.$number, ['attempt' => $number, 'provider_message_id' => $result->messageId]);
            if ($result->outcome === 'rejected') {
                app(EligibilityService::class)->suppress($contact, 'hard_bounce', 'Simulated permanent rejection.');
                $service->event($r, 'hard_bounce', 'hard-bounce-'.$r->id, ['reason' => 'Simulated permanent failure']);
            }
            if ($r->status === 'deferred') {
                self::dispatch($r->id)->delay($r->next_attempt_at)->afterCommit();
            }
        });
        $service->complete($initial->campaign_id);
    }

    public function failed(?\Throwable $e): void
    {
        $id = DB::transaction(function () {
            $initial = CampaignRecipient::find($this->recipientId);
            if (! $initial) {
                return null;
            }$c = Campaign::whereKey($initial->campaign_id)->lockForUpdate()->firstOrFail();
            $r = CampaignRecipient::whereKey($initial->id)->lockForUpdate()->first();
            if (in_array($r->status, ['pending', 'deferred'])) {
                $r->update(['status' => 'failed']);
                app(CampaignService::class)->event($r, 'failed', 'worker-failed-'.$r->id, ['reason' => 'Worker retries exhausted.']);
            } if (config('dispatch.provider') !== 'simulation') {
                $c->update(['status' => 'failed']);
            }

            return $c->id;
        });
        if ($id) {
            app(CampaignService::class)->complete($id);
        }
    }
}
