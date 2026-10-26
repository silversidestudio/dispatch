<?php

namespace App\Jobs;

use App\Models\WebhookBatch;
use App\Services\WebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessSendGridWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 90;

    public function __construct(public int $batchId) {}

    public function backoff(): array
    {
        return [5, 30, 120];
    }

    public function handle(WebhookService $service): void
    {
        $b = WebhookBatch::find($this->batchId);
        if (! $b || $b->status === 'completed') {
            return;
        }foreach ($b->events as $e) {
            $service->ingest($e);
        }$b->update(['status' => 'completed']);
    }

    public function failed(?\Throwable $e): void
    {
        WebhookBatch::whereKey($this->batchId)->update(['status' => 'failed']);
    }
}
