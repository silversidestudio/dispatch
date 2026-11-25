<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Organisation;
use App\Models\Suppression;
use App\Models\SuppressionImport;
use App\Models\SuppressionImportRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SuppressionImportService
{
    public function read(string $path): array
    {
        $handle = fopen(Storage::path($path), 'r');
        if (! $handle) {
            throw ValidationException::withMessages(['file' => 'The CSV file could not be read.']);
        }

        try {
            $headers = fgetcsv($handle, 0, ',', '"', '');
            if (! $headers || count($headers) > 30) {
                throw ValidationException::withMessages(['file' => 'The CSV needs a header row and no more than 30 columns.']);
            }
            $headers = array_map(fn ($value) => mb_substr(trim((string) $value, "\xEF\xBB\xBF \t\r\n"), 0, 120), $headers);
            if (count(array_unique($headers)) !== count($headers)) {
                throw ValidationException::withMessages(['file' => 'Column names must be unique.']);
            }
            $rows = [];
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($row === [null] || $row === []) {
                    continue;
                }
                $rows[] = $row;
                if (count($rows) > 10000) {
                    throw ValidationException::withMessages(['file' => 'A suppression import can contain up to 10,000 rows. Split a larger file into multiple imports.']);
                }
            }

            return [$headers, $rows];
        } finally {
            fclose($handle);
        }
    }

    public function process(SuppressionImport $import): void
    {
        if ($import->status === 'completed' || $import->status === 'preview') {
            return;
        }
        if (Organisation::whereKey($import->organisation_id)->value('status') !== 'active') {
            throw new \RuntimeException('Company suspended; suppression import paused.');
        }
        if ($import->email_column === null) {
            throw new \RuntimeException('Choose the CSV email column before processing this import.');
        }

        [$headers, $rows] = $this->read($import->path);
        if ($import->email_column >= count($headers)) {
            throw new \RuntimeException('The selected email column is no longer available in the CSV.');
        }

        $import->update(['status' => 'processing', 'failure' => null]);
        foreach ($rows as $index => $row) {
            DB::transaction(function () use ($import, $headers, $row, $index) {
                $locked = SuppressionImport::whereKey($import->id)->lockForUpdate()->firstOrFail();
                $rowNumber = $index + 2;
                if (SuppressionImportRow::where('suppression_import_id', $import->id)->where('row_number', $rowNumber)->exists()) {
                    return;
                }

                $email = strtolower(trim((string) ($row[$import->email_column] ?? '')));
                $error = count($row) !== count($headers) ? 'Column count differs from the header.' : null;
                if (! $error && Validator::make(['email' => $email], ['email' => 'required|email:rfc|max:254'])->fails()) {
                    $error = 'Enter a valid email address.';
                }

                $status = 'rejected';
                if (! $error) {
                    $evidence = trim((string) $import->evidence);
                    $details = 'Imported from '.$import->source.' (suppression import #'.$import->id.')'.($evidence !== '' ? ': '.$evidence : '.');
                    $suppression = Suppression::firstOrCreate(
                        ['organisation_id' => $import->organisation_id, 'email' => $email],
                        ['reason' => 'unsubscribed', 'evidence' => $details],
                    );

                    if ($suppression->wasRecentlyCreated) {
                        $status = 'added';
                        $contact = Contact::where('organisation_id', $import->organisation_id)->where('email', $email)->lockForUpdate()->first();
                        if ($contact) {
                            app(EligibilityService::class)->suppress($contact, 'unsubscribed', $details);
                        }
                    } else {
                        $status = 'existing';
                        // Preserve the original reason and evidence. The historical opt-out
                        // must remain effective even if an older record used another reason.
                        Contact::where('organisation_id', $import->organisation_id)->where('email', $email)
                            ->where('eligibility', '!=', $suppression->reason)
                            ->update(['eligibility' => $suppression->reason]);
                    }
                }

                SuppressionImportRow::create([
                    'suppression_import_id' => $import->id,
                    'row_number' => $rowNumber,
                    'status' => $status,
                    'error' => $error,
                ]);
                $locked->increment('processed');
                $locked->increment(match ($status) {
                    'added' => 'added',
                    'existing' => 'existing',
                    default => 'rejected',
                });
            }, 3);
        }

        $import->update(['status' => 'completed', 'failure' => null]);
        AuditLog::record('suppression_import.completed', $import->organisation_id, [
            'suppression_import_id' => $import->id,
            'source' => $import->source,
            'added' => $import->added,
            'existing' => $import->existing,
            'rejected' => $import->rejected,
        ]);
    }
}
