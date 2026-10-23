<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Organisation;
use App\Services\CampaignService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class PumpCampaigns implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function handle(): void
    {
        Campaign::whereIn('status', ['scheduled', 'processing'])->where('scheduled_at', '<=', now())->whereIn('organisation_id', Organisation::where('status', 'active')->select('id'))->orderBy('id')->chunkById(50, function ($campaigns) {
            foreach ($campaigns as $campaign) {
                DB::transaction(function () use ($campaign) {
                    $c = Campaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
                    if (! in_array($c->status, ['scheduled', 'processing'])) {
                        return;
                    }$c->update(['status' => 'processing']);
                    $c->recipients()->whereIn('status', ['pending', 'deferred'])->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))->select('id')->chunkById(200, function ($rows) {
                        foreach ($rows as $r) {
                            DeliverRecipient::dispatch($r->id)->afterCommit();
                        }
                    });
                });
                app(CampaignService::class)->complete($campaign->id);
            }
        });
    }
}
