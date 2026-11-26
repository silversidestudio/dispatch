<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\EligibilityRecord;
use App\Models\ImportRow;
use App\Models\MailingList;
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
            $headers = $this->headers($h);
            $rows = [];
            while (($row = fgetcsv($h, 0, ',', '"', '')) !== false) {
                if ($row === [null]) {
                    continue;
                }$rows[] = $row;
                if (count($rows) > config('dispatch.import_max_rows', 25000)) {
                    throw ValidationException::withMessages(['file' => 'This CSV exceeds the contact import row limit. Split it into smaller files.']);
                }
            }

            return [$headers, $rows];
        } finally {
            fclose($h);
        }
    }

    public function inspect(string $path): array
    {
        $h = fopen(Storage::path($path), 'r');
        if (! $h) {
            throw ValidationException::withMessages(['file' => 'Cannot read CSV.']);
        }
        try {
            $headers = $this->headers($h);
            $total = 0;
            while (($row = fgetcsv($h, 0, ',', '"', '')) !== false) {
                if ($row === [null]) {
                    continue;
                }
                $total++;
                if ($total > config('dispatch.import_max_rows', 25000)) {
                    throw ValidationException::withMessages(['file' => 'This CSV exceeds the contact import row limit. Split it into smaller files.']);
                }
            }

            return [$headers, $total];
        } finally {
            fclose($h);
        }
    }

    public function sample(string $path, int $limit = 10): array
    {
        $h = fopen(Storage::path($path), 'r');
        if (! $h) {
            throw ValidationException::withMessages(['file' => 'Cannot read CSV.']);
        }
        try {
            $headers = $this->headers($h);
            $rows = [];
            while (count($rows) < $limit && ($row = fgetcsv($h, 0, ',', '"', '')) !== false) {
                if ($row !== [null]) {
                    $rows[] = $row;
                }
            }

            return [$headers, $rows];
        } finally {
            fclose($h);
        }
    }

    private function headers($handle): array
    {
        $headers = fgetcsv($handle, 0, ',', '"', '');
        if (! $headers || count($headers) > 30) {
            throw ValidationException::withMessages(['file' => 'CSV must have 1–30 columns and a header.']);
        }
        $headers = array_map(fn ($v) => mb_substr(trim((string) $v, "\xEF\xBB\xBF \t\r\n"), 0, 120), $headers);
        if (count(array_unique($headers)) !== count($headers)) {
            throw ValidationException::withMessages(['file' => 'Column names must be unique.']);
        }

        return $headers;
    }

    public function process(ContactImport $import): void
    {
        while ($this->processChunk($import->fresh())) {}
        if ($import->fresh()->status === 'completed') {
            app(InAppNotificationService::class)->importFinished($import->fresh());
        }
    }

    public function processChunk(ContactImport $import): bool
    {
        if (in_array($import->status, ['completed', 'erased']) || $import->status === 'preview') {
            return false;
        }
        if (Organisation::whereKey($import->organisation_id)->value('status') !== 'active') {
            throw new \RuntimeException('Company suspended; import paused.');
        }
        $mailingList = $import->mailing_list_id
            ? MailingList::where('organisation_id', $import->organisation_id)->whereNull('archived_at')->find($import->mailing_list_id)
            : null;
        if ($import->mailing_list_id && ! $mailingList) {
            throw new \RuntimeException('The selected mailing list is no longer active. Select an active list and retry the import.');
        }
        $h = fopen(Storage::path($import->path), 'r');
        if (! $h) {
            throw new \RuntimeException('The uploaded CSV is no longer available.');
        }
        try {
            $headers = $this->headers($h);
            if ($headers !== $import->headers) {
                throw new \RuntimeException('The uploaded CSV headers have changed.');
            }
            $offset = (int) $import->cursor_offset;
            if ($offset > 0) {
                fseek($h, $offset);
            } else {
                $offset = ftell($h);
                $import->update(['status' => 'processing', 'cursor_offset' => $offset]);
            }
            $rowNumber = (int) $import->cursor_row;
            $processedInChunk = 0;
            while (($row = fgetcsv($h, 0, ',', '"', '')) !== false) {
                $rowNumber++;
                if ($row === [null]) {
                    continue;
                }
                $nextOffset = ftell($h);
                $this->processRow($import, $row, $rowNumber, $headers, $mailingList, $nextOffset);
                $processedInChunk++;
                if ($processedInChunk >= (int) config('dispatch.contact_import.chunk_size', 250)) {
                    return true;
                }
            }
        } finally {
            fclose($h);
        }
        $fresh = ContactImport::findOrFail($import->id);
        if ($fresh->status !== 'completed') {
            $fresh->update(['status' => 'completed', 'failure' => null]);
            AuditLog::record('import.completed', $fresh->organisation_id, ['import_id' => $fresh->id]);
        }

        return false;
    }

    private function processRow(ContactImport $import, array $row, int $rowNumber, array $headers, ?MailingList $mailingList, int $nextOffset): void
    {
        DB::transaction(function () use ($import, $row, $rowNumber, $headers, $mailingList, $nextOffset) {
            $locked = ContactImport::whereKey($import->id)->lockForUpdate()->firstOrFail();
            if (ImportRow::where('contact_import_id', $import->id)->where('row_number', $rowNumber)->exists()) {
                $locked->update(['cursor_offset' => max((int) $locked->cursor_offset, $nextOffset), 'cursor_row' => max((int) $locked->cursor_row, $rowNumber)]);

                return;
            }
            $data = ['organisation_id' => $import->organisation_id, 'status' => 'active', 'eligibility' => 'unknown', 'source' => $import->source];
            foreach ($import->mapping ?? [] as $field => $index) {
                if (in_array($field, self::FIELDS, true) && $index !== null && $index !== '') {
                    $data[$field] = trim((string) ($row[(int) $index] ?? ''));
                }
            }
            $data['email'] = strtolower($data['email'] ?? '');
            $validation = Validator::make($data, ['email' => 'required|email:rfc|max:254', 'first_name' => 'nullable|string|max:120', 'last_name' => 'nullable|string|max:120', 'company_name' => 'nullable|string|max:255', 'telephone' => 'nullable|string|max:60']);
            $error = count($row) !== count($headers) ? 'Column count differs from header.' : ($validation->fails() ? $validation->errors()->first() : null);
            $existing = ! $error ? Contact::withTrashed()->where('organisation_id', $import->organisation_id)->where('email', $data['email'])->first() : null;
            if (! $error && $existing?->trashed()) {
                $error = 'This email address exists in Recently deleted contacts. Restore the existing contact instead.';
            }
            if (! $error) {
                try {
                    if ($existing) {
                        $contact = $existing;
                    } else {
                        $suppression = Suppression::where('organisation_id', $import->organisation_id)->where('email', $data['email'])->first();
                        $data['eligibility'] = $suppression?->reason ?? 'csv_import';
                        $contact = Contact::create($data);
                        if (! $suppression) {
                            EligibilityRecord::create([
                                'organisation_id' => $import->organisation_id, 'contact_id' => $contact->id,
                                'user_id' => $import->user_id, 'basis' => 'csv_import',
                                'evidence' => 'Imported from CSV using the workspace eligibility setting. The source was recorded; permission to email was not verified.',
                                'method' => 'csv_import', 'recorded_at' => now(),
                                'metadata' => ['source' => $import->source, 'import_id' => $import->id, 'row_number' => $rowNumber],
                            ]);
                            AuditLog::create(['action' => 'contact.import_eligibility_assigned', 'organisation_id' => $import->organisation_id, 'user_id' => $import->user_id, 'metadata' => ['contact_id' => $contact->id, 'import_id' => $import->id, 'basis' => 'csv_import']]);
                        }
                    }
                    if ($mailingList) {
                        $membership = $contact->lists()->syncWithoutDetaching([$mailingList->id]);
                        if ($existing && $membership['attached']) {
                            AuditLog::create(['action' => 'list.membership_imported', 'organisation_id' => $import->organisation_id, 'user_id' => $import->user_id, 'metadata' => ['contact_id' => $contact->id, 'list_id' => $mailingList->id, 'import_id' => $import->id]]);
                        }
                    }
                } catch (UniqueConstraintViolationException) {
                    $error = 'Duplicate email; existing record preserved.';
                }
            }
            ImportRow::create(['contact_import_id' => $import->id, 'row_number' => $rowNumber, 'email' => mb_substr($data['email'], 0, 254), 'status' => $error ? 'rejected' : 'imported', 'error' => $error]);
            $locked->increment('processed');
            $locked->increment($error ? 'rejected' : 'successful');
            $locked->update(['cursor_offset' => $nextOffset, 'cursor_row' => $rowNumber]);
        });
    }
}
