<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSendGridWebhook;
use App\Models\Organisation;
use App\Models\WebhookBatch;
use App\Services\WebhookService;
use Illuminate\Http\Request;

class SendGridWebhookController extends Controller
{
    public function __invoke(Request $r, WebhookService $s, Organisation $organisation)
    {
        $body = $r->getContent();
        abort_if(strlen($body) > 1048576, 413);
        abort_unless($s->verify($body, (string) $r->header('X-Twilio-Email-Event-Webhook-Timestamp'), (string) $r->header('X-Twilio-Email-Event-Webhook-Signature'), $organisation->sendgrid_webhook_key), 401);
        try {
            $events = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            abort(422);
        }abort_unless(is_array($events) && array_is_list($events) && count($events) <= 1000, 422);
        foreach ($events as $event) {
            abort_unless(is_array($event), 422);
        }
        $batch = WebhookBatch::firstOrCreate(['digest' => hash('sha256', $organisation->id.':'.$body)], ['events' => array_map(fn ($e) => [...$s->normalise($e), 'organisation_id' => $organisation->id], $events), 'status' => 'queued']);
        if ($batch->status !== 'completed') {
            ProcessSendGridWebhook::dispatch($batch->id);
        }

        return response()->json(['accepted' => true], 202);
    }
}
