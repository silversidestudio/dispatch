<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\DeliveryEvent;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class UnsubscribeService
{
    public function token(Contact $contact, ?CampaignRecipient $recipient = null): string
    {
        return rtrim(strtr(base64_encode(Crypt::encryptString(json_encode(['recipient_id' => $recipient?->id, 'organisation_id' => $contact->organisation_id, 'contact_id' => $contact->id, 'email' => $contact->email], JSON_THROW_ON_ERROR))), '+/', '-_'), '=');
    }

    public function url(Contact $contact, ?CampaignRecipient $recipient = null): string
    {
        return URL::signedRoute('unsubscribe.show', ['token' => $this->token($contact, $recipient)], absolute: false);
    }

    public function oneClickUrl(Contact $contact, ?CampaignRecipient $recipient = null): string
    {
        return URL::signedRoute('unsubscribe.oneclick', ['token' => $this->token($contact, $recipient)], absolute: false);
    }

    public function contact(string $token): Contact
    {
        $d = json_decode(Crypt::decryptString(base64_decode(strtr($token, '-_', '+/'), true)), true, 512, JSON_THROW_ON_ERROR);

        return Contact::where('organisation_id', $d['organisation_id'])->where('email', $d['email'])->findOrFail($d['contact_id']);
    }

    public function apply(string $token): void
    {
        $c = $this->contact($token);
        $data = json_decode(Crypt::decryptString(base64_decode(strtr($token, '-_', '+/'), true)), true, 512, JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($c, $data) {
            app(EligibilityService::class)->suppress($c, 'unsubscribed', 'Recipient unsubscribe confirmation.');
            if ($data['recipient_id'] ?? null) {
                $r = CampaignRecipient::where('organisation_id', $c->organisation_id)->where('contact_id', $c->id)->where('email', $c->email)->findOrFail($data['recipient_id']);
                $campaign = Campaign::where('organisation_id', $c->organisation_id)->findOrFail($r->campaign_id);
                $live = ($campaign->snapshot['delivery_mode'] ?? 'simulation') === 'sendgrid_live';
                DeliveryEvent::firstOrCreate(['event_key' => 'dispatch-unsubscribe-'.$r->id], ['organisation_id' => $r->organisation_id, 'campaign_id' => $r->campaign_id, 'campaign_recipient_id' => $r->id, 'type' => 'unsubscribe', 'provider' => $live ? 'sendgrid' : 'simulation', 'simulated' => ! $live, 'occurred_at' => now(), 'metadata' => ['source' => 'dispatch_confirmation']]);
            }

        });
    }
}
