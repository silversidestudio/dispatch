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
        $handle = $this->open($path);
        try {
            $headers = $this->headers($handle);
            $rows = [];
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($row === [null] || $row === []) {
                    continue;
                }
                $rows[] = $row;
                if (count($rows) > config('dispatch.import_max_rows', 25000)) {
                    throw ValidationException::withMessages(['file' => 'This file exceeds the suppression import row limit. Split it into smaller files.']);
                }
            }

            return [$headers, $rows];
        } finally {
            fclose($handle);
        }
    }

    public function inspect(string $path): array
    {
        $handle = $this->open($path);
        try {
            $headers = $this->headers($handle);
            $total = 0;
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($row === [null] || $row === []) {
                    continue;
                }
                $total++;
                if ($total > config('dispatch.import_max_rows', 25000)) {
                    throw ValidationException::withMessages(['file' => 'This file exceeds the suppression import row limit. Split it into smaller files.']);
                }
            }

            return [$headers, $total];
        } finally {
            fclose($handle);
        }
    }

    public function sample(string $path, int $limit = 10): array
    {
        $handle = $this->open($path);
        try {
            $this->headers($handle);
            $rows = [];
            while (count($rows) < $limit && ($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($row !== [null] && $row !== []) {
                    $rows[] = $row;
                }
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function open(string $path)
    {
        $handle = fopen(Storage::path($path), 'r');
        if (! $handle) {
            throw ValidationException::withMessages(['file' => 'The CSV file could not be read.']);
        }

        return $handle;
    }

    private function headers($handle): array
    {
        $headers = fgetcsv($handle, 0, ',', '"', '');
        if (! $headers || count($headers) > 30) {
            throw ValidationException::withMessages(['file' => 'The CSV needs a header row and no more than 30 columns.']);
        }
        $headers = array_map(fn ($value) => mb_substr(trim((string) $value, "\xEF\xBB\xBF \t\r\n"), 0, 120), $headers);
        if (count(array_unique($headers)) !== count($headers)) {
            throw ValidationException::withMessages(['file' => 'Column names must be unique.']);
        }

        return $headers;
    }

    public function process(SuppressionImport $import): void
    {
        while ($this->processChunk($import->fresh())) {}
    }

    public function processChunk(SuppressionImport $import): bool
    {
        if ($import->status === 'completed' || $import->status === 'preview') {
            return false;
        }
        if (Organisation::whereKey($import->organisation_id)->value('status') !== 'active') {
            throw new \RuntimeException('Company suspended; suppression import paused.');
        }
        if ($import->email_column === null) {
            throw new \RuntimeException('Choose the CSV email column before processing this import.');
        }

        $handle = fopen(Storage::path($import->path), 'r');
        if (! $handle) {
            throw new \RuntimeException('The uploaded CSV is no longer available.');
        }
        try {
            $headers = $this->headers($handle);
            if ($headers !== $import->headers || $import->email_column >= count($headers)) {
                throw new \RuntimeException('The selected email column is no longer available in this CSV.');
            }

            $offset = (int) $import->cursor_offset;
            if ($offset > 0) {
                fseek($handle, $offset);
            } else {
                $offset = ftell($handle);
                $import->update(['status' => 'processing', 'failure' => null, 'cursor_offset' => $offset]);
            }

            $rowNumber = (int) $import->cursor_row;
            $processedInChunk = 0;
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $rowNumber++;
                if ($row === [null] || $row === []) {
                    continue;
                }
                $this->processRow($import, $headers, $row, $rowNumber, ftell($handle));
                $processedInChunk++;
                if ($processedInChunk >= (int) config('dispatch.suppression_import.chunk_size', 250)) {
                    return true;
                }
            }
        } finally {
            fclose($handle);
        }

        $fresh = SuppressionImport::findOrFail($import->id);
        if ($fresh->status !== 'completed') {
            $fresh->update(['status' => 'completed', 'failure' => null]);
            AuditLog::record('suppression_import.completed', $fresh->organisation_id, [
                'suppression_import_id' => $fresh->id,
                'source' => $fresh->source,
                'added' => $fresh->added,
                'existing' => $fresh->existing,
                'rejected' => $fresh->rejected,
            ]);
        }

        return false;
    }

    private function processRow(SuppressionImport $import, array $headers, array $row, int $rowNumber, int $nextOffset): void
    {
        DB::transaction(function () use ($import, $headers, $row, $rowNumber, $nextOffset) {
            $locked = SuppressionImport::whereKey($import->id)->lockForUpdate()->firstOrFail();
            if (SuppressionImportRow::where('suppression_import_id', $import->id)->where('row_number', $rowNumber)->exists()) {
                $locked->update(['cursor_offset' => max((int) $locked->cursor_offset, $nextOffset), 'cursor_row' => max((int) $locked->cursor_row, $rowNumber)]);

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
            $locked->update(['cursor_offset' => $nextOffset, 'cursor_row' => $rowNumber]);
        }, 3);
    }
}
