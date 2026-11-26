<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Organisation;
use App\Models\SendingIdentity;

class ProviderReadiness
{
    public function errors(Campaign $c, bool $submission = false): array
    {
        $mode = $c->snapshot['delivery_mode'] ?? $c->delivery_mode ?? 'simulation';
        if ($mode === 'simulation') {
            return ! in_array(config('dispatch.provider'), ['simulation', 'simulated', 'sendgrid'], true) ? ['Unsupported delivery provider.'] : [];
        }
        $errors = [];
        if (! in_array($mode, ['sendgrid_sandbox', 'sendgrid_live'], true)) {
            return ['Unsupported delivery mode.'];
        }
        $o = Organisation::findOrFail($c->organisation_id);
        if (config('dispatch.provider') !== 'sendgrid' || ! config('dispatch.sendgrid.enabled') || ! $o->sendgrid_api_key) {
            $errors[] = 'SendGrid is not connected to this workspace. A workspace owner can add the account details in Sending setup.';
        }
        if ($c->snapshot && (! isset($c->snapshot['sendgrid_credential']) || $c->snapshot['sendgrid_credential'] !== $o->sendGridCredentialFingerprint())) {
            $errors[] = 'The SendGrid account details changed after this campaign was prepared. Create a new campaign to use the updated account.';
        }
        if ($mode === 'sendgrid_live') {
            $errors = [...$errors, ...app(SendGridPilotGuard::class)->configurationErrors($c->organisation_id)];
            $identity = SendingIdentity::where('organisation_id', $c->organisation_id)->find($c->sending_identity_id);
            if (! $identity || ! $identity->provider_authorised || $identity->verification_status !== 'verified' || ! $identity->verified_at || $identity->verified_at->lt(now()->subDays(config('dispatch.sendgrid.identity_max_age_days'))) || ($c->snapshot && $identity->email !== $c->snapshot['sender_email'])) {
                $errors[] = 'The sender address needs a current SendGrid verification before this campaign can be sent.';
            }
            if (! config('dispatch.sendgrid.live_enabled')) {
                $errors[] = 'Live sending is turned off for this installation. Ask your system administrator if it should be enabled.';
            }
            if ($submission && ! $c->live_authorised_at) {
                $errors[] = 'Review the campaign and confirm live sending before scheduling.';
            }
            if (! str_starts_with(config('app.url'), 'https://')) {
                $errors[] = 'Live campaigns need a public HTTPS address. Ask your system administrator to check the site setup.';
            }
            if (! $o->sendgrid_webhook_key) {
                $errors[] = 'SendGrid delivery feedback is not set up for this workspace. Ask your system administrator for help.';
            }
        }
        $open = $c->snapshot['open_tracking'] ?? $c->open_tracking;
        $click = $c->snapshot['click_tracking'] ?? $c->click_tracking;
        if (($open || $click) && (! $o->tracking_reviewed_at || ($open && ! $o->open_tracking_enabled) || ($click && ! $o->click_tracking_enabled))) {
            $errors[] = 'Tracking is off until a workspace owner reviews the privacy settings.';
        }

        return $errors;
    }
}
