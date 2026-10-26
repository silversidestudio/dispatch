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
        if (config('dispatch.provider') !== 'sendgrid' || ! config('dispatch.sendgrid.enabled') || ! config('dispatch.sendgrid.api_key')) {
            $errors[] = 'SendGrid is not configured and enabled.';
        }
        if ($mode === 'sendgrid_live') {
            $identity = SendingIdentity::where('organisation_id', $c->organisation_id)->find($c->sending_identity_id);
            if (! $identity || ! $identity->provider_authorised || $identity->verification_status !== 'verified' || ! $identity->verified_at || $identity->verified_at->lt(now()->subDays(config('dispatch.sendgrid.identity_max_age_days'))) || ($c->snapshot && $identity->email !== $c->snapshot['sender_email'])) {
                $errors[] = 'A currently verified and platform-authorised SendGrid identity is required.';
            }
            if (! config('dispatch.sendgrid.live_enabled')) {
                $errors[] = 'Live sending is disabled.';
            }
            if ($submission && ! $c->live_authorised_at) {
                $errors[] = 'Campaign live sending has not been explicitly confirmed.';
            }
            if (! str_starts_with(config('app.url'), 'https://')) {
                $errors[] = 'Public HTTPS application URL is required for live sending.';
            }
            if (! config('dispatch.sendgrid.webhook_key')) {
                $errors[] = 'Signed webhook configuration is required.';
            }
        }
        $o = Organisation::findOrFail($c->organisation_id);
        $open = $c->snapshot['open_tracking'] ?? $c->open_tracking;
        $click = $c->snapshot['click_tracking'] ?? $c->click_tracking;
        if (($open || $click) && (! $o->tracking_reviewed_at || ($open && ! $o->open_tracking_enabled) || ($click && ! $o->click_tracking_enabled))) {
            $errors[] = 'Tracking requires documented company privacy approval and enabled controls.';
        }

        return $errors;
    }
}
