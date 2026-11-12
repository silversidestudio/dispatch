<?php

namespace App\Delivery;

use App\Models\Organisation;
use App\Services\SendGridPilotGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class SendGridDeliveryProvider implements DeliveryProvider
{
    public function submit(DeliveryMessage $m, int $attempt): SubmissionResult
    {
        $organisation = $m->organisationId ? Organisation::find($m->organisationId) : null;
        $key = $organisation?->sendgrid_api_key;
        if (! config('dispatch.sendgrid.enabled') || ! $key) {
            throw new \RuntimeException('SendGrid is not configured.');
        }
        if (! $m->credentialFingerprint || $m->credentialFingerprint !== $organisation->sendGridCredentialFingerprint()) {
            return new SubmissionResult('not_submitted', '', null, 'company_credentials_changed');
        }
        if (! $m->sandbox && (! config('dispatch.sendgrid.live_enabled') || ! $m->liveAuthorised)) {
            throw new \RuntimeException('Live sending is disabled or unauthorised.');
        }
        if (! $m->sandbox) {
            app(SendGridPilotGuard::class)->reserve($m->to, $m->idempotencyKey, $m->organisationId);
        }
        $payload = ['personalizations' => [['to' => [['email' => $m->to]], 'custom_args' => ['dispatch_attempt' => $m->idempotencyKey]]], 'from' => ['email' => $m->from, 'name' => $m->fromName], 'subject' => $m->subject, 'content' => [['type' => 'text/plain', 'value' => $m->text], ['type' => 'text/html', 'value' => $m->html]], 'mail_settings' => ['sandbox_mode' => ['enable' => $m->sandbox]], 'tracking_settings' => ['open_tracking' => ['enable' => $m->openTracking], 'click_tracking' => ['enable' => $m->clickTracking, 'enable_text' => $m->clickTracking], 'subscription_tracking' => ['enable' => false]], 'headers' => $m->headers];
        if ($m->replyTo) {
            $payload['reply_to'] = ['email' => $m->replyTo];
        }
        try {
            $response = Http::withToken($key)->acceptJson()->connectTimeout(5)->timeout(config('dispatch.sendgrid.timeout'))->post('https://api.sendgrid.com/v3/mail/send', $payload);
        } catch (ConnectionException) {
            return new SubmissionResult('uncertain', '', null, 'network_outcome_unknown');
        }
        $status = $response->status();
        $id = substr((string) $response->header('X-Message-Id'), 0, 255);
        if ($m->sandbox && $status === 200) {
            return new SubmissionResult('sandbox_validated', $id, $status, 'sandbox_validation');
        }
        if (! $m->sandbox && $status === 202) {
            return new SubmissionResult('accepted', $id, $status, 'sendgrid_accepted');
        }
        if ($status === 429) {
            return new SubmissionResult('deferred', $id, $status, 'rate_limited', min(3600, max(5, (int) $response->header('Retry-After'))));
        }
        if ($status >= 500 || $status === 408 || ($status >= 200 && $status < 300)) {
            return new SubmissionResult('uncertain', $id, $status, 'provider_outcome_unknown');
        }

        return new SubmissionResult('rejected', $id, $status, in_array($status, [401, 403]) ? 'provider_authentication_failed' : 'provider_request_rejected');
    }
}
