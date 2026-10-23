<?php

namespace App\Services;

use App\Jobs\PumpCampaigns;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\DeliveryEvent;
use App\Models\Organisation;
use App\Models\Segment;
use App\Models\SendingIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CampaignService
{
    public function preflight(Campaign $c): array
    {
        $errors = [];
        $o = Organisation::findOrFail($c->organisation_id);
        $identity = SendingIdentity::where('organisation_id', $o->id)->find($c->sending_identity_id);
        if ($o->status !== 'active') {
            $errors[] = 'Company is suspended.';
        }if (! $o->postal_address) {
            $errors[] = 'Company postal address is required for sender identification.';
        }
        if (! $identity) {
            $errors[] = 'A company sending identity is required.';
        }
        if (! trim($c->subject) || ! trim(strip_tags($c->html)) || ! trim($c->text_body)) {
            $errors[] = 'Subject, HTML and plain text are required.';
        }
        if (! str_contains($c->html, '{{unsubscribe_url}}') || ! str_contains($c->text_body, '{{unsubscribe_url}}')) {
            $errors[] = 'Include {{unsubscribe_url}} in HTML and plain text.';
        }
        if (! str_contains($c->html, '{{sender_address}}')) {
            $errors[] = 'Include {{sender_address}} in the HTML footer.';
        }
        if (config('dispatch.provider') !== 'simulation') {
            $errors[] = 'Only the simulation adapter is authorised.';
        }
        $all = app(AudienceService::class)->query($o->id, $c->audience_type, $c->audience_id)->count();
        $eligible = app(AudienceService::class)->query($o->id, $c->audience_type, $c->audience_id, true)->count();
        if ($eligible === 0) {
            $errors[] = 'No eligible recipients in the selected audience.';
        }if ($eligible > config('dispatch.max_recipients')) {
            $errors[] = 'Simulation limit is 10,000 recipients.';
        }

        return ['errors' => $errors, 'total' => $all, 'eligible' => $eligible, 'excluded' => $all - $eligible, 'provider' => 'Local simulation only; identities remain unverified'];
    }

    public function ready(Campaign $campaign): void
    {
        DB::transaction(function () use ($campaign) {
            $c = Campaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $this->requireState($c, ['draft']);
            $p = $this->preflight($c);
            if ($p['errors']) {
                throw ValidationException::withMessages(['campaign' => implode(' ', $p['errors'])]);
            }$c->update(['status' => 'ready']);
            AuditLog::record('campaign.ready', $c->organisation_id, ['campaign_id' => $c->id]);
        });
    }

    public function schedule(Campaign $campaign, \DateTimeInterface $when): void
    {
        DB::transaction(function () use ($campaign, $when) {
            $c = Campaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $this->requireState($c, ['ready']);
            $p = $this->preflight($c);
            if ($p['errors']) {
                throw ValidationException::withMessages(['campaign' => implode(' ', $p['errors'])]);
            }$identity = SendingIdentity::where('organisation_id', $c->organisation_id)->findOrFail($c->sending_identity_id);
            $o = Organisation::findOrFail($c->organisation_id);
            $c->snapshot = ['subject' => $c->subject, 'preview_text' => $c->preview_text, 'html' => app(EmailContentService::class)->clean($c->html), 'text_body' => $c->text_body, 'sender_name' => $identity->name, 'sender_email' => $identity->email, 'reply_to' => $c->reply_to ?: $identity->reply_to, 'sender_address' => $o->postal_address, 'provider' => 'simulation', 'unsubscribe_base' => rtrim(config('app.url'), '/')];
            $suppressed = app(AudienceService::class)->query($c->organisation_id, $c->audience_type, $c->audience_id)->whereExists(fn ($q) => $q->selectRaw('1')->from('suppressions')->whereColumn('suppressions.organisation_id', 'contacts.organisation_id')->whereColumn('suppressions.email', 'contacts.email'))->count();
            $snapshot = $c->snapshot;
            $snapshot['suppressed_at_snapshot'] = $suppressed;
            $snapshot['other_ineligible_at_snapshot'] = $p['excluded'] - $suppressed;
            $snapshot['audience'] = ['type' => $c->audience_type, 'id' => $c->audience_id, 'rules' => $c->audience_type === 'segment' ? Segment::findOrFail($c->audience_id)->rules : null];
            $c->snapshot = $snapshot;
            $c->status = 'scheduled';
            $c->scheduled_at = $when;
            $c->audience_count = $p['total'];
            $c->excluded_count = $p['excluded'];
            $c->save();
            app(AudienceService::class)->query($c->organisation_id, $c->audience_type, $c->audience_id, true)->orderBy('contacts.id')->chunkById(200, function ($contacts) use ($c) {
                foreach ($contacts as $contact) {
                    $r = CampaignRecipient::firstOrCreate(['campaign_id' => $c->id, 'contact_id' => $contact->id], ['organisation_id' => $c->organisation_id, 'email' => $contact->email, 'personalisation' => ['first_name' => $contact->first_name ?? '', 'last_name' => $contact->last_name ?? '', 'email' => $contact->email], 'status' => 'pending']);
                    $this->event($r, 'queued', 'queued-'.$r->id);
                }
            });
            AuditLog::record('campaign.scheduled', $c->organisation_id, ['campaign_id' => $c->id, 'recipient_count' => $c->recipients()->count()]);
            PumpCampaigns::dispatch()->delay($when)->afterCommit();
        });
    }

    public function cancel(Campaign $campaign): void
    {
        DB::transaction(function () use ($campaign) {
            $c = Campaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $this->requireState($c, ['draft', 'ready', 'scheduled', 'processing']);
            $c->update(['status' => 'cancelled']);
            $c->recipients()->whereIn('status', ['pending', 'deferred'])->update(['status' => 'cancelled']);
            AuditLog::record('campaign.cancelled', $c->organisation_id, ['campaign_id' => $c->id]);
        });
    }

    public function requireState(Campaign $c, array $states): void
    {
        if (! in_array($c->status, $states, true)) {
            throw ValidationException::withMessages(['campaign' => 'Invalid transition from '.$c->status.'.']);
        }
    }

    public function event(CampaignRecipient $r, string $type, string $key, array $metadata = []): void
    {
        DeliveryEvent::firstOrCreate(['event_key' => $key], ['organisation_id' => $r->organisation_id, 'campaign_id' => $r->campaign_id, 'campaign_recipient_id' => $r->id, 'type' => $type, 'simulated' => true, 'metadata' => $metadata]);
    }

    public function complete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $c = Campaign::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($c->status === 'processing' && ! $c->recipients()->whereIn('status', ['pending', 'deferred'])->exists()) {
                $c->update(['status' => 'completed', 'completed_at' => now()]);
                AuditLog::record('campaign.completed', $c->organisation_id, ['campaign_id' => $c->id]);
            }
        });
    }
}
