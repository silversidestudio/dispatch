<?php

namespace App\Jobs;

use App\Models\SuppressionImport;
use App\Services\SuppressionImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessSuppressionImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $suppressionImportId) {}

    public function backoff(): array
    {
        return [5, 30, 60];
    }

    public function handle(SuppressionImportService $service): void
    {
        $service->process(SuppressionImport::findOrFail($this->suppressionImportId));
    }

    public function failed(?\Throwable $exception): void
    {
        SuppressionImport::whereKey($this->suppressionImportId)->update([
            'status' => 'failed',
            'failure' => 'The import stopped before all rows were processed. Review the errors and retry; completed rows will be skipped.',
        ]);
    }
}
