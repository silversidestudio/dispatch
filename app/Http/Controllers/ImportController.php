<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessContactImport;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\ImportRow;
use App\Models\Organisation;
use App\Services\CsvImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ImportController extends Controller
{
    public function index(Organisation $organisation)
    {
        Gate::authorize('view', $organisation);

        return Inertia::render('imports', ['company' => $organisation, 'imports' => ContactImport::where('organisation_id', $organisation->id)->latest()->paginate(15), 'canManage' => Gate::allows('manageCampaigns', $organisation)]);
    }

    public function upload(Request $r, Organisation $organisation)
    {
        Gate::authorize('manageCampaigns', $organisation);
        $r->validate(['file' => 'required|file|max:5120|mimes:csv,txt', 'source' => 'required|string|max:255']);
        $path = $r->file('file')->store('imports');
        try {
            [$headers,$rows] = app(CsvImportService::class)->read($path);
        } catch (\Throwable $e) {
            Storage::delete($path);
            throw $e;
        }$import = ContactImport::create(['organisation_id' => $organisation->id, 'user_id' => $r->user()->id, 'path' => $path, 'headers' => $headers, 'total' => count($rows), 'source' => $r->input('source')]);

        return redirect("/organisations/$organisation->id/imports/$import->id");
    }

    public function show(Organisation $organisation, ContactImport $import)
    {
        Gate::authorize('view', $organisation);
        abort_unless($import->organisation_id === $organisation->id, 404);
        [$headers,$rows] = Storage::exists($import->path) ? app(CsvImportService::class)->read($import->path) : [$import->headers, []];
        $sample = array_map(function ($row) use ($organisation, $headers) {
            $emails = array_filter($row, fn ($v) => filter_var($v, FILTER_VALIDATE_EMAIL));

            return ['values' => $row, 'potential_duplicate' => Contact::where('organisation_id', $organisation->id)->whereIn('email', array_map(fn ($s) => strtolower(trim($s)), $emails))->exists(), 'column_count_valid' => count($row) === count($headers)];
        }, array_slice($rows, 0, 10));

        return Inertia::render('import-detail', ['company' => $organisation, 'import' => $import, 'sample' => $sample, 'rows' => ImportRow::where('contact_import_id', $import->id)->latest('id')->limit(30)->get(), 'canManage' => Gate::allows('manageCampaigns', $organisation)]);
    }

    public function start(Request $r, Organisation $organisation, ContactImport $import)
    {
        Gate::authorize('manageCampaigns', $organisation);
        abort_unless($import->organisation_id === $organisation->id, 404);
        DB::transaction(function () use ($r, $import) {
            $i = ContactImport::whereKey($import->id)->lockForUpdate()->firstOrFail();
            if (! in_array($i->status, ['preview', 'failed'])) {
                return;
            }if ($i->status === 'preview') {
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

        return redirect("/organisations/$organisation->id/imports/$import->id")->with('success', 'Import queued. New contacts remain ineligible until evidence is recorded.');
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
