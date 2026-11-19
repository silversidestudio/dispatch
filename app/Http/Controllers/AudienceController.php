<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\MailingList;
use App\Models\Organisation;
use App\Models\Segment;
use App\Services\AudienceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AudienceController extends Controller
{
    public function index(Request $request, Organisation $organisation)
    {
        Gate::authorize('view', $organisation);

        $view = $request->validate(['view' => 'nullable|in:active,archived,trash'])['view'] ?? 'active';
        $lists = MailingList::query()->where('organisation_id', $organisation->id);
        if ($view === 'trash') {
            $lists->onlyTrashed();
        } else {
            $lists->whereNull('deleted_at');
            $view === 'active' ? $lists->whereNull('archived_at') : $lists->whereNotNull('archived_at');
        }

        return Inertia::render('audiences', ['company' => $organisation, 'lists' => $lists->withCount('contacts')->latest()->get(), 'view' => $view, 'segments' => Segment::where('organisation_id', $organisation->id)->get()->map(fn ($s) => [...$s->toArray(), 'total' => app(AudienceService::class)->query($organisation->id, 'segment', $s->id)->count(), 'eligible' => app(AudienceService::class)->query($organisation->id, 'segment', $s->id, true)->count()]), 'canManage' => Gate::allows('manageCampaigns', $organisation)]);
    }

    public function listStore(Request $r, Organisation $organisation)
    {
        Gate::authorize('manageCampaigns', $organisation);
        $d = $r->validate(['name' => 'required|string|max:120', 'description' => 'nullable|string|max:2000']);
        $l = MailingList::create(['organisation_id' => $organisation->id, ...$d]);
        AuditLog::record('list.created', $organisation->id, ['list_id' => $l->id]);

        return back()->with('success', 'Mailing list created.');
    }

    public function listUpdate(Request $r, Organisation $organisation, MailingList $list)
    {
        Gate::authorize('manageCampaigns', $organisation);
        abort_unless($list->organisation_id === $organisation->id, 404);
        abort_if($list->archived_at, 422, 'Restore this list before editing it.');
        $list->update($r->validate(['name' => 'required|string|max:120', 'description' => 'nullable|string|max:2000']));
        AuditLog::record('list.updated', $organisation->id, ['list_id' => $list->id]);

        return back()->with('success', 'List updated.');
    }

    public function listArchive(Organisation $organisation, MailingList $list)
    {
        Gate::authorize('manageCampaigns', $organisation);
        abort_unless($list->organisation_id === $organisation->id, 404);
        abort_if($list->archived_at, 422, 'This list is already archived.');
        $inUse = Campaign::where('organisation_id', $organisation->id)->where('audience_type', 'list')->where('audience_id', $list->id)->whereIn('status', ['draft', 'ready', 'scheduled', 'processing', 'paused'])->exists();
        abort_if($inUse, 422, 'This list is selected by a campaign in progress. Complete or cancel that campaign before archiving the list.');
        $list->update(['archived_at' => now()]);
        AuditLog::record('list.archived', $organisation->id, ['list_id' => $list->id]);

        return back()->with('success', 'List archived. Its contacts and past campaign history are unchanged.');
    }

    public function listRestore(Organisation $organisation, int $list)
    {
        Gate::authorize('manageCampaigns', $organisation);
        $record = MailingList::withTrashed()->where('organisation_id', $organisation->id)->findOrFail($list);
        if ($record->trashed()) {
            abort_if(MailingList::where('organisation_id', $organisation->id)->where('name', $record->name)->exists(), 422, 'A list with this name already exists. Rename it before restoring this one.');
            $record->restore();
        }
        $record->update(['archived_at' => null]);
        AuditLog::record('list.restored', $organisation->id, ['list_id' => $record->id]);

        return back()->with('success', 'List restored.');
    }

    public function listDelete(Organisation $organisation, MailingList $list)
    {
        Gate::authorize('manageCampaigns', $organisation);
        abort_unless($list->organisation_id === $organisation->id, 404);
        abort_if(Campaign::withTrashed()->where('organisation_id', $organisation->id)->where('audience_type', 'list')->where('audience_id', $list->id)->exists(), 422, 'This list is part of campaign history and cannot be deleted. Archive it instead.');
        $segmentUsesList = Segment::where('organisation_id', $organisation->id)->get(['rules'])->contains(fn (Segment $segment) => collect($segment->rules)->contains(fn (array $rule) => ($rule['field'] ?? null) === 'list_id' && (int) ($rule['value'] ?? 0) === $list->id));
        abort_if($segmentUsesList, 422, 'A dynamic segment uses this list. Archive the list instead.');
        $list->delete();
        AuditLog::record('list.deleted', $organisation->id, ['list_id' => $list->id]);

        return back()->with('success', 'List moved to Recently deleted. Its membership and contact records are retained.');
    }

    public function show(Request $r, Organisation $organisation, MailingList $list)
    {
        Gate::authorize('view', $organisation);
        abort_unless($list->organisation_id === $organisation->id, 404);
        $search = $r->validate(['search' => 'nullable|string|max:120'])['search'] ?? '';

        return Inertia::render('list-detail', ['company' => $organisation, 'list' => $list, 'contacts' => Contact::where('organisation_id', $organisation->id)->where('email', 'like', "%$search%")->withExists(['lists as member' => fn ($q) => $q->where('mailing_lists.id', $list->id)])->orderBy('email')->paginate(25)->withQueryString(), 'eligible' => $list->archived_at ? 0 : app(AudienceService::class)->query($organisation->id, 'list', $list->id, true)->count(), 'canManage' => Gate::allows('manageCampaigns', $organisation) && ! $list->archived_at, 'history' => AuditLog::where('organisation_id', $organisation->id)->where('metadata->list_id', $list->id)->latest('id')->limit(20)->get()]);
    }

    public function membership(Request $r, Organisation $organisation, MailingList $list)
    {
        Gate::authorize('manageCampaigns', $organisation);
        abort_unless($list->organisation_id === $organisation->id, 404);
        abort_if($list->archived_at, 422, 'Restore this list before changing its members.');
        $d = $r->validate(['ids' => 'required|array|min:1|max:200', 'ids.*' => 'integer', 'action' => 'required|in:add,remove']);
        abort_unless(Contact::where('organisation_id', $organisation->id)->whereIn('id', $d['ids'])->count() === count(array_unique($d['ids'])), 404);
        DB::transaction(function () use ($list, $d, $organisation) {
            if ($d['action'] === 'add') {
                $list->contacts()->syncWithoutDetaching($d['ids']);
            } else {
                $list->contacts()->detach($d['ids']);
            }foreach ($d['ids'] as $id) {
                AuditLog::record('list.membership_'.$d['action'], $organisation->id, ['list_id' => $list->id, 'contact_id' => $id]);
            }
        });

        return back()->with('success', 'List membership updated. Eligibility and suppression are unchanged.');
    }

    public function segment(Request $r, Organisation $organisation, ?Segment $segment = null)
    {
        Gate::authorize('manageCampaigns', $organisation);
        if ($segment) {
            abort_unless($segment->organisation_id === $organisation->id, 404);
        }$d = $r->validate(['name' => 'required|string|max:120', 'rules' => 'required|array']);
        $d['rules'] = app(AudienceService::class)->validateRules($d['rules'], $organisation->id);
        if ($segment) {
            $segment->update($d);
        } else {
            $segment = Segment::create(['organisation_id' => $organisation->id, ...$d]);
        }AuditLog::record('segment.saved', $organisation->id, ['segment_id' => $segment->id]);

        return back()->with('success', 'Dynamic segment saved.');
    }

    public function preview(Organisation $organisation, Segment $segment)
    {
        Gate::authorize('view', $organisation);
        abort_unless($segment->organisation_id === $organisation->id, 404);

        return response()->json(['total' => app(AudienceService::class)->query($organisation->id, 'segment', $segment->id)->count(), 'eligible' => app(AudienceService::class)->query($organisation->id, 'segment', $segment->id, true)->count(), 'sample' => app(AudienceService::class)->query($organisation->id, 'segment', $segment->id)->limit(20)->get(['id', 'email', 'eligibility'])]);
    }
}
