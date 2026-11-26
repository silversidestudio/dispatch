<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessContactImport;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\ImportRow;
use App\Models\MailingList;
use App\Models\Organisation;
use App\Services\CsvImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Validation\ValidationException;

class ImportController extends Controller
{
    public function index(Organisation $organisation)
    {
        Gate::authorize('view', $organisation);

        return Inertia::render('imports', ['company' => $organisation, 'imports' => ContactImport::where('organisation_id', $organisation->id)->latest()->paginate(15), 'lists' => MailingList::where('organisation_id', $organisation->id)->whereNull('archived_at')->orderBy('name')->get(['id', 'name']), 'maxRows' => (int) config('dispatch.import_max_rows', 25000), 'canManage' => Gate::allows('manageCampaigns', $organisation)]);
    }

    public function upload(Request $r, Organisation $organisation)
    {
        Gate::authorize('manageCampaigns', $organisation);
        $d = $r->validate(['file' => 'required|file|max:5120|mimes:csv,txt', 'source' => 'required|string|max:255', 'mailing_list_id' => 'required|integer|exists:mailing_lists,id']);
        $list = MailingList::where('organisation_id', $organisation->id)->whereNull('archived_at')->findOrFail($d['mailing_list_id']);
        $path = $r->file('file')->store('imports');
        try {
            [$headers,$total] = app(CsvImportService::class)->inspect($path);
        } catch (\Throwable $e) {
            Storage::delete($path);
            throw $e;
        }$import = ContactImport::create(['organisation_id' => $organisation->id, 'mailing_list_id' => $list->id, 'user_id' => $r->user()->id, 'path' => $path, 'headers' => $headers, 'total' => $total, 'source' => $r->input('source')]);

        return redirect("/organisations/$organisation->id/imports/$import->id");
    }

    public function show(Organisation $organisation, ContactImport $import)
    {
        Gate::authorize('view', $organisation);
        abort_unless($import->organisation_id === $organisation->id, 404);
        [$headers,$rows] = Storage::exists($import->path) ? app(CsvImportService::class)->sample($import->path) : [$import->headers, []];
        $sample = array_map(function ($row) use ($organisation, $headers) {
            $emails = array_filter($row, fn ($v) => filter_var($v, FILTER_VALIDATE_EMAIL));

            return ['values' => $row, 'potential_duplicate' => Contact::where('organisation_id', $organisation->id)->whereIn('email', array_map(fn ($s) => strtolower(trim($s)), $emails))->exists(), 'column_count_valid' => count($row) === count($headers)];
        }, array_slice($rows, 0, 10));

        $selectedList = $import->mailing_list_id ? MailingList::where('organisation_id', $organisation->id)->find($import->mailing_list_id) : null;

        return Inertia::render('import-detail', ['company' => $organisation, 'import' => $import, 'sample' => $sample, 'lists' => MailingList::where('organisation_id', $organisation->id)->whereNull('archived_at')->orderBy('name')->get(['id', 'name']), 'selectedList' => $selectedList ? ['id' => $selectedList->id, 'name' => $selectedList->name, 'archived' => (bool) $selectedList->archived_at] : null, 'maxRows' => (int) config('dispatch.import_max_rows', 25000), 'rows' => ImportRow::where('contact_import_id', $import->id)->latest('id')->limit(30)->get(), 'canManage' => Gate::allows('manageCampaigns', $organisation)]);
    }

    public function start(Request $r, Organisation $organisation, ContactImport $import)
    {
        Gate::authorize('manageCampaigns', $organisation);
        abort_unless($import->organisation_id === $organisation->id, 404);
        $d = $r->validate(['mailing_list_id' => 'required|integer|exists:mailing_lists,id', 'confirm_import' => 'required|accepted']);
        $list = MailingList::where('organisation_id', $organisation->id)->whereNull('archived_at')->findOrFail($d['mailing_list_id']);
        DB::transaction(function () use ($r, $import, $list) {
            $i = ContactImport::whereKey($import->id)->lockForUpdate()->firstOrFail();
            if (! in_array($i->status, ['preview', 'failed'])) {
                return;
            }
            if ($i->processed > 0 && $i->mailing_list_id && (int) $i->mailing_list_id !== (int) $list->id) {
                throw ValidationException::withMessages(['mailing_list_id' => 'This import has already started and must continue with its original mailing list.']);
            }
            $i->mailing_list_id = $list->id;
            if ($i->status === 'preview' || ! $i->mapping) {
                $d = $r->validate(['mapping' => 'required|array', 'mapping.email' => 'required|integer|min:0', 'mapping.*' => 'nullable|integer|min:0']);
                $used = [];
                foreach ($d['mapping'] as $field => $index) {
                    abort_unless(in_array($field, CsvImportService::FIELDS, true), 422);
                    if ($index === null) {
                        continue;
                    }abort_unless($index < count($i->headers) && ! in_array($index, $used, true), 422, 'Invalid or repeated column mapping.');
                    $used[] = $index;
                }$i->mapping = $d['mapping'];
            }$i->status = 'queued';
            $i->save();
            ProcessContactImport::dispatch($i->id)->afterCommit();
        });

        return redirect("/organisations/$organisation->id/imports/$import->id")->with('success', "Import queued for {$list->name}. New contacts will be added to that list; existing contacts and suppressions are preserved.");
    }

    public function errors(Organisation $organisation, ContactImport $import)
    {
        Gate::authorize('view', $organisation);
        abort_unless($import->organisation_id === $organisation->id, 404);

        return response()->streamDownload(function () use ($import) {
            $h = fopen('php://output', 'w');
            fputcsv($h, ['row', 'email', 'error']);
            ImportRow::where('contact_import_id', $import->id)->where('status', 'rejected')->orderBy('row_number')->chunkById(200, function ($rows) use ($h) {
                foreach ($rows as $r) {
                    $safe = fn ($s) => preg_match('/^[\s]*[=+@\-\t\r]/', (string) $s) ? "'".$s : $s;
                    fputcsv($h, [$r->row_number, $safe($r->email), $safe($r->error)]);
                }
            });
            fclose($h);
        }, 'import-errors.csv', ['Content-Type' => 'text/csv']);
    }
}
