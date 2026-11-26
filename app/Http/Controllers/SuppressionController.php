<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSuppressionImport;
use App\Models\AuditLog;
use App\Models\Organisation;
use App\Models\Suppression;
use App\Models\SuppressionImport;
use App\Models\SuppressionImportRow;
use App\Services\SuppressionImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SuppressionController extends Controller
{
    public function index(Request $request, Organisation $organisation)
    {
        Gate::authorize('view', $organisation);

        return Inertia::render('suppressions', [
            'company' => $organisation,
            'suppressions' => Suppression::where('organisation_id', $organisation->id)->latest('id')->paginate(25)->withQueryString(),
            'imports' => SuppressionImport::where('organisation_id', $organisation->id)->latest('id')->limit(10)->get(),
            'total' => Suppression::where('organisation_id', $organisation->id)->count(),
            'canManage' => Gate::allows('manageCampaigns', $organisation),
        ]);
    }

    public function upload(Request $request, Organisation $organisation)
    {
        Gate::authorize('manageCampaigns', $organisation);
        $data = $request->validate([
            'file' => 'required|file|max:5120|mimes:csv,txt',
            'source' => 'required|string|max:120',
            'evidence' => 'nullable|string|max:1000',
        ]);
        $path = $request->file('file')->store('suppression-imports');
        try {
            [$headers, $rows] = app(SuppressionImportService::class)->read($path);
        } catch (\Throwable $exception) {
            Storage::delete($path);
            throw $exception;
        }
        if ($rows === []) {
            Storage::delete($path);

            return back()->withErrors(['file' => 'The CSV has no email rows to import.']);
        }

        $import = SuppressionImport::create([
            'organisation_id' => $organisation->id,
            'user_id' => $request->user()->id,
            'path' => $path,
            'headers' => $headers,
            'source' => $data['source'],
            'evidence' => $data['evidence'] ?? null,
            'total' => count($rows),
        ]);
        AuditLog::record('suppression_import.uploaded', $organisation->id, ['suppression_import_id' => $import->id, 'rows' => count($rows)]);

        return redirect("/organisations/$organisation->id/suppressions/imports/$import->id");
    }

    public function show(Organisation $organisation, SuppressionImport $import)
    {
        Gate::authorize('view', $organisation);
        abort_unless($import->organisation_id === $organisation->id, 404);

        $sample = [];
        if (Storage::exists($import->path) && in_array($import->status, ['preview', 'failed'], true)) {
            [, $rows] = app(SuppressionImportService::class)->read($import->path);
            $sample = array_slice($rows, 0, 10);
        }

        return Inertia::render('suppression-import-detail', [
            'company' => $organisation,
            'import' => $import,
            'sample' => $sample,
            'rows' => SuppressionImportRow::where('suppression_import_id', $import->id)->latest('id')->limit(30)->get(),
            'canManage' => Gate::allows('manageCampaigns', $organisation),
        ]);
    }

    public function start(Request $request, Organisation $organisation, SuppressionImport $import)
    {
        Gate::authorize('manageCampaigns', $organisation);
        abort_unless($import->organisation_id === $organisation->id, 404);
        $data = $request->validate(['email_column' => 'required|integer|min:0']);
        abort_unless($data['email_column'] < count($import->headers), 422, 'Choose a column from this CSV.');

        $import->update(['email_column' => $data['email_column'], 'status' => 'queued', 'failure' => null]);
        ProcessSuppressionImport::dispatch($import->id)->afterCommit();

        return redirect("/organisations/$organisation->id/suppressions/imports/$import->id")->with('success', 'Import started. These addresses will be excluded from all campaigns in this workspace.');
    }

    public function errors(Organisation $organisation, SuppressionImport $import)
    {
        Gate::authorize('view', $organisation);
        abort_unless($import->organisation_id === $organisation->id, 404);

        return response()->streamDownload(function () use ($import) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['row', 'error']);
            SuppressionImportRow::where('suppression_import_id', $import->id)->where('status', 'rejected')->orderBy('row_number')->chunkById(200, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [$row->row_number, $row->error]);
                }
            });
            fclose($handle);
        }, 'suppression-import-errors.csv', ['Content-Type' => 'text/csv']);
    }
}
