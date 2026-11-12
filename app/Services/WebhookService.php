<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\DeliveryAttempt;
use App\Models\DeliveryEvent;
use App\Models\Suppression;
use App\Models\WebhookReceipt;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WebhookService
{
    public function verify(string $body, string $timestamp, string $signature, ?string $key): bool
    {
        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > config('dispatch.sendgrid.signature_tolerance')) {
            return false;
        }
        if (! $key) {
            return false;
        }
        $pem = str_contains($key, 'BEGIN PUBLIC KEY') ? $key : "-----BEGIN PUBLIC KEY-----\n".chunk_split($key, 64, "\n")."-----END PUBLIC KEY-----\n";
        $public = @openssl_pkey_get_public($pem);
        $sig = base64_decode($signature, true);
        if (! $public || ! $sig) {
            return false;
        }
        $details = openssl_pkey_get_details($public);
        if (($details['type'] ?? null) !== OPENSSL_KEYTYPE_EC) {
            return false;
        }

        return openssl_verify($timestamp.$body, $sig, $public, OPENSSL_ALGO_SHA256) === 1;
    }

    public function safeUrl(?string $url): ?string
    {
        if (! $url || strlen($url) > 4096) {
            return null;
        }$p = parse_url($url);
        if (! $p || ! in_array(strtolower($p['scheme'] ?? ''), ['https', 'http']) || empty($p['host'])) {
            return null;
        }
        $path = $p['path'] ?? '/';
        for ($i = 0; $i < 3; $i++) {
            $path = rawurldecode($path);
        }
        if (preg_match('~unsubscribe|one-click|reset-password|verify-email|/login|/admin|/invitations|/asm/|subscription~i', $path)) {
            return null;
        }

        // Retain only the destination origin: even URL paths can contain tokens or personal information.
        return strtolower($p['scheme']).'://'.strtolower($p['host']).(isset($p['port']) ? ':'.$p['port'] : '').' / link-'.substr(hash_hmac('sha256', $path, config('app.key')), 0, 12);
    }

    public function normalise(array $event): array
    {
        $messageId = substr((string) ($event['sg_message_id'] ?? ''), 0, 255);

        return ['event_id' => substr((string) ($event['sg_event_id'] ?? ''), 0, 255), 'correlation' => substr((string) ($event['dispatch_attempt'] ?? ''), 0, 64), 'type' => ($event['event'] ?? '') === 'bounce' && ($event['type'] ?? '') === 'blocked' ? 'blocked' : substr((string) ($event['event'] ?? 'unknown'), 0, 40), 'timestamp' => (int) ($event['timestamp'] ?? 0), 'provider_message_id' => $messageId, 'email_hash' => isset($event['email']) ? hash('sha256', strtolower(trim((string) $event['email']))) : null, 'http_response_code' => preg_match('/^[245][0-9]{2}/', (string) ($event['response'] ?? $event['status'] ?? ''), $m) ? $m[0] : null, 'bounce_type' => in_array($event['type'] ?? '', ['bounce', 'blocked']) ? $event['type'] : null, 'url' => $this->safeUrl(isset($event['url']) ? (string) $event['url'] : null)];
    }

    public function ingest(array $e): void
    {
        $key = hash('sha256', (isset($e['organisation_id']) ? $e['organisation_id'].':' : '').($e['event_id'] ?: json_encode($e)));
        DB::transaction(function () use ($e, $key) {
            $a = DeliveryAttempt::where('correlation_id', $e['correlation'])->where('provider', 'sendgrid')->when(isset($e['organisation_id']), fn ($q) => $q->where('organisation_id', $e['organisation_id']))->first();
            if (! $a) {
                WebhookReceipt::firstOrCreate(['event_key' => $key], ['status' => 'quarantined', 'reason' => 'unmatched_correlation']);

                return;
            }
            $initial = CampaignRecipient::find($a->campaign_recipient_id);
            if (! $initial) {
                WebhookReceipt::firstOrCreate(['event_key' => $key], ['status' => 'quarantined', 'reason' => 'recipient_missing']);

                return;
            }
            $c = Campaign::whereKey($initial->campaign_id)->lockForUpdate()->firstOrFail();
            $r = CampaignRecipient::whereKey($initial->id)->lockForUpdate()->firstOrFail();
            $a = DeliveryAttempt::whereKey($a->id)->lockForUpdate()->firstOrFail();
            if ($a->organisation_id !== $r->organisation_id || $c->organisation_id !== $r->organisation_id || ($c->snapshot['delivery_mode'] ?? '') !== 'sendgrid_live' || ! $a->started_at || ($e['email_hash'] && ! hash_equals(hash('sha256', strtolower($r->email)), $e['email_hash']))) {
                WebhookReceipt::firstOrCreate(['event_key' => $key], ['status' => 'quarantined', 'reason' => 'correlation_mismatch']);

                return;
            }
            if (! $e['event_id'] || $e['timestamp'] <= 0 || $e['timestamp'] > time() + 300) {
                WebhookReceipt::firstOrCreate(['event_key' => $key], ['status' => 'quarantined', 'reason' => 'invalid_event_identity_or_timestamp']);

                return;
            }
            if (WebhookReceipt::where('event_key', $key)->exists()) {
                return;
            }
            $known = ['processed', 'delivered', 'deferred', 'bounce', 'blocked', 'dropped', 'spamreport', 'unsubscribe', 'group_unsubscribe', 'group_resubscribe', 'open', 'click'];
            $type = $e['type'];
            $ignored = ! in_array($type, $known, true) || $type === 'group_resubscribe' || ($type === 'open' && ! ($c->snapshot['open_tracking'] ?? false)) || ($type === 'click' && (! ($c->snapshot['click_tracking'] ?? false) || ! $e['url']));
            WebhookReceipt::create(['event_key' => $key, 'delivery_attempt_id' => $a->id, 'status' => $ignored ? 'ignored' : 'processed', 'reason' => $ignored ? 'unsupported_or_disabled_event' : null]);
            if ($ignored) {
                return;
            }
            $when = $e['timestamp'] > 0 && $e['timestamp'] <= time() + 300 ? Carbon::createFromTimestampUTC($e['timestamp']) : now();
            DeliveryEvent::create(['organisation_id' => $r->organisation_id, 'campaign_id' => $c->id, 'campaign_recipient_id' => $r->id, 'delivery_attempt_id' => $a->id, 'type' => $type, 'provider' => 'sendgrid', 'simulated' => false, 'event_key' => 'sg-event-'.$key, 'occurred_at' => $when, 'provider_message_id' => $e['provider_message_id'], 'url' => $e['url'], 'metadata' => ['smtp_code' => $e['http_response_code'], 'bounce_type' => $e['bounce_type']]]);
            if ($a->outcome !== 'accepted') {
                $a->update(['outcome' => 'accepted', 'finished_at' => now(), 'provider_message_id' => $e['provider_message_id'], 'diagnostic_code' => 'confirmed_by_signed_event']);
                $r->update(['status' => 'accepted', 'provider_message_id' => $e['provider_message_id']]);
            }
            $rank = ['queued' => 0, 'processed' => 1, 'deferred' => 2, 'delivered' => 3, 'blocked' => 4, 'dropped' => 4, 'bounce' => 5];
            $status = $r->delivery_status;
            if (isset($rank[$type]) && ($rank[$type] > ($rank[$status] ?? 0) || ($rank[$type] === ($rank[$status] ?? 0) && (! $r->last_provider_event_at || $when->gte(Carbon::parse($r->last_provider_event_at)))))) {
                $r->delivery_status = $type;
            }
            $r->last_provider_event_at = max($when->toDateTimeString(), (string) $r->last_provider_event_at);
            $r->save();
            $reason = match ($type) {
                'spamreport' => 'complaint','unsubscribe','group_unsubscribe' => 'unsubscribed','bounce' => ($e['bounce_type'] === 'blocked' ? null : 'hard_bounce'),default => null
            };
            if ($reason) {
                $contact = Contact::where('organisation_id', $r->organisation_id)->where('email', $r->email)->find($r->contact_id);
                if ($contact) {
                    app(EligibilityService::class)->suppress($contact, $reason, 'Verified SendGrid feedback.');
                } else {
                    Suppression::firstOrCreate(['organisation_id' => $r->organisation_id, 'email' => $r->email], ['reason' => $reason, 'evidence' => 'Verified SendGrid feedback for snapshotted address.']);
                }
            }
        });
    }
}
