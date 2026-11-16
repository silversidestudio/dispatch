<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\EligibilityRecord;
use App\Models\ImportRow;
use App\Models\Organisation;
use App\Models\Suppression;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CsvImportService
{
    const FIELDS = ['email', 'first_name', 'last_name', 'company_name', 'telephone'];

    public function read(string $path): array
    {
        $h = fopen(Storage::path($path), 'r');
        if (! $h) {
            throw ValidationException::withMessages(['file' => 'Cannot read CSV.']);
        }
        try {
            $headers = fgetcsv($h, 0, ',', '"', '');
            if (! $headers || count($headers) > 30) {
                throw ValidationException::withMessages(['file' => 'CSV must have 1–30 columns and a header.']);
            }
            $headers = array_map(fn ($v) => mb_substr(trim((string) $v, "\xEF\xBB\xBF \t\r\n"), 0, 120), $headers);
            if (count(array_unique($headers)) !== count($headers)) {
                throw ValidationException::withMessages(['file' => 'Column names must be unique.']);
            }
            $rows = [];
            while (($row = fgetcsv($h, 0, ',', '"', '')) !== false) {
                if ($row === [null]) {
                    continue;
                }$rows[] = $row;
                if (count($rows) > 10000) {
                    throw ValidationException::withMessages(['file' => 'Limit is 10,000 rows.']);
                }
            }

            return [$headers, $rows];
        } finally {
            fclose($h);
        }
    }

    public function process(ContactImport $import): void
    {
        if (in_array($import->status, ['completed', 'erased']) || $import->status === 'preview') {
            return;
        }
        if (Organisation::whereKey($import->organisation_id)->value('status') !== 'active') {
            throw new \RuntimeException('Company suspended; import paused.');
        }
        [$headers,$rows] = $this->read($import->path);
        $import->update(['status' => 'processing']);
        foreach ($rows as $i => $row) {
            DB::transaction(function () use ($import, $row, $i, $headers) {
                ContactImport::whereKey($import->id)->lockForUpdate()->firstOrFail();
                if (ImportRow::where('contact_import_id', $import->id)->where('row_number', $i + 2)->exists()) {
                    return;
                }
                $data = ['organisation_id' => $import->organisation_id, 'status' => 'active', 'eligibility' => 'unknown', 'source' => $import->source];
                foreach ($import->mapping as $field => $index) {
                    if (in_array($field, self::FIELDS, true) && $index !== null && $index !== '') {
                        $data[$field] = trim((string) ($row[(int) $index] ?? ''));
                    }
                }
                $data['email'] = strtolower($data['email'] ?? '');
                $validation = Validator::make($data, ['email' => 'required|email:rfc|max:254', 'first_name' => 'nullable|string|max:120', 'last_name' => 'nullable|string|max:120', 'company_name' => 'nullable|string|max:255', 'telephone' => 'nullable|string|max:60']);
                $error = count($row) !== count($headers) ? 'Column count differs from header.' : ($validation->fails() ? $validation->errors()->first() : null);
                if (! $error && Contact::where('organisation_id', $import->organisation_id)->where('email', $data['email'])->exists()) {
                    $error = 'Duplicate email in this company; existing record preserved.';
                }
                if (! $error) {
                    try {
                        $suppression = Suppression::where('organisation_id', $import->organisation_id)->where('email', $data['email'])->first();
                        $data['eligibility'] = $suppression?->reason ?? 'csv_import';
                        $contact = Contact::create($data);
                        if (! $suppression) {
                            EligibilityRecord::create([
                                'organisation_id' => $import->organisation_id, 'contact_id' => $contact->id,
                                'user_id' => $import->user_id, 'basis' => 'csv_import',
                                'evidence' => 'Imported from CSV using the workspace eligibility setting. The source was recorded; permission to email was not verified.',
                                'method' => 'csv_import', 'recorded_at' => now(),
                                'metadata' => ['source' => $import->source, 'import_id' => $import->id, 'row_number' => $i + 2],
                            ]);
                            AuditLog::create(['action' => 'contact.import_eligibility_assigned', 'organisation_id' => $import->organisation_id, 'user_id' => $import->user_id, 'metadata' => ['contact_id' => $contact->id, 'import_id' => $import->id, 'basis' => 'csv_import']]);
                        }
                    } catch (UniqueConstraintViolationException) {
                        $error = 'Duplicate email; existing record preserved.';
                    }
                }
                ImportRow::create(['contact_import_id' => $import->id, 'row_number' => $i + 2, 'email' => mb_substr($data['email'], 0, 254), 'status' => $error ? 'rejected' : 'imported', 'error' => $error]);
                $import->increment('processed');
                $import->increment($error ? 'rejected' : 'successful');
            });
        }
        $import->update(['status' => 'completed', 'failure' => null]);
        AuditLog::record('import.completed', $import->organisation_id, ['import_id' => $import->id]);
        app(InAppNotificationService::class)->importFinished($import->fresh());
    }
}
