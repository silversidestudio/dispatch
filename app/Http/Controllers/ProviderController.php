<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DeliveryAttempt;
use App\Models\Organisation;
use App\Models\SendingIdentity;
use App\Services\SendGridSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

class ProviderController extends Controller
{
    public function credentials(Request $r, Organisation $organisation)
    {
        Gate::authorize('update', $organisation);
        $d = $r->validate(['sendgrid_api_key' => 'required|string|max:255', 'sendgrid_webhook_key' => 'nullable|string|max:2048']);
        DB::transaction(function () use ($organisation, $d) {
            $o = Organisation::whereKey($organisation->id)->lockForUpdate()->firstOrFail();
            $o->forceFill(['sendgrid_api_key' => $d['sendgrid_api_key'], 'sendgrid_webhook_key' => $d['sendgrid_webhook_key'] ?? null])->save();
            SendingIdentity::where('organisation_id', $o->id)->update(['provider_authorised' => false, 'verification_status' => 'unverified', 'verified_at' => null, 'provider_domain_id' => null, 'spf_status' => 'not_checked', 'dkim_status' => 'not_checked', 'dmarc_status' => 'not_checked']);
            AuditLog::record('sendgrid.credentials_updated', $o->id);
        });

        return back()->with('success', 'SendGrid details saved. Verify your sender address again before live sending.');
    }

    public function verify(Request $r, Organisation $organisation, SendingIdentity $identity)
    {
        if (! Gate::allows('platform')) {
            Gate::authorize('update', $organisation);
        }
        abort_unless($identity->organisation_id === $organisation->id, 404);
        $d = $r->validate(['domain_id' => 'required|integer|min:1', 'confirmation' => 'required|in:AUTHORISE']);
        abort_unless(config('dispatch.sendgrid.enabled') && $organisation->sendgrid_api_key, 422, 'SendGrid credentials are not configured. No external verification performed.');
        try {
            $response = Http::withToken($organisation->sendgrid_api_key)->timeout(10)->get('https://api.sendgrid.com/v3/whitelabel/domains/'.$d['domain_id']);
        } catch (\Throwable) {
            abort(422, 'Provider status unavailable.');
        }
        $data = $response->json();
        $verified = $response->successful() && ($data['valid'] ?? false) === true && strtolower($data['domain'] ?? '') === strtolower($identity->domain);
        DB::transaction(function () use ($organisation, $identity, $d, $verified) {
            $current = Organisation::whereKey($organisation->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->sendGridCredentialFingerprint() === $organisation->sendGridCredentialFingerprint(), 409, 'Credentials changed; verify again.');
            $identity->update(['provider_domain_id' => $d['domain_id'], 'provider_authorised' => $verified, 'verification_status' => $verified ? 'verified' : 'unverified', 'verified_at' => $verified ? now() : null, 'spf_status' => $verified ? 'provider_authenticated' : 'not_checked', 'dkim_status' => $verified ? 'provider_authenticated' : 'not_checked', 'dmarc_status' => 'not_checked']);
            AuditLog::record('identity.provider_status_reviewed', $organisation->id, ['identity_id' => $identity->id, 'verified' => $verified]);
        });

        return back()->with('success', $verified ? 'Stored SendGrid domain status verified and identity authorised. DMARC still requires independent review.' : 'Identity is not verified; live delivery remains blocked.');
    }

    public function tracking(Request $r, Organisation $organisation)
    {
        Gate::authorize('update', $organisation);
        $d = $r->validate(['open_tracking_enabled' => 'required|boolean', 'click_tracking_enabled' => 'required|boolean', 'evidence' => 'required|string|min:20|max:4000', 'confirmation' => 'required|in:REVIEWED']);
        $organisation->forceFill(['open_tracking_enabled' => $d['open_tracking_enabled'], 'click_tracking_enabled' => $d['click_tracking_enabled'], 'tracking_review_evidence' => $d['evidence'], 'tracking_reviewed_at' => now()])->save();
        AuditLog::record('tracking.review_recorded', $organisation->id, ['open' => $d['open_tracking_enabled'], 'click' => $d['click_tracking_enabled']]);

        return back()->with('success', 'Tracking review recorded. Each campaign still requires an explicit opt-in.');
    }

    public function reconcile(Request $r, Organisation $organisation, DeliveryAttempt $attempt)
    {
        Gate::authorize('update', $organisation);
        abort_unless($attempt->organisation_id === $organisation->id, 404);
        $d = $r->validate(['decision' => 'required|in:confirmed_not_submitted,closed_without_retry', 'evidence' => 'required|string|min:20|max:4000', 'confirmation' => 'required|in:RECONCILE']);
        app(SendGridSubmissionService::class)->reconcile($attempt, $d['decision'], $d['evidence']);

        return back()->with('success', 'Uncertain submission reconciled and audited.');
    }
}
