<?php

namespace App\Jobs;

use App\Models\ContactImport;
use App\Services\CsvImportService;
use App\Services\InAppNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessContactImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $importId) {}

    public function backoff(): array
    {
        return [5, 30, 60];
    }

    public function handle(CsvImportService $service): void
    {
        $service->process(ContactImport::findOrFail($this->importId));
    }

    public function failed(?\Throwable $e): void
    {
        ContactImport::whereKey($this->importId)->update(['status' => 'failed', 'failure' => 'Import interrupted. Review the import and retry; processed rows will be skipped.']);
        $import = ContactImport::find($this->importId);
        if ($import) {
            app(InAppNotificationService::class)->importFinished($import);
        }
    }
}
