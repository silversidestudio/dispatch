<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
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
    public function index(Organisation $organisation)
    {
        Gate::authorize('view', $organisation);

        return Inertia::render('audiences', ['company' => $organisation, 'lists' => MailingList::where('organisation_id', $organisation->id)->withCount('contacts')->latest()->get(), 'segments' => Segment::where('organisation_id', $organisation->id)->get()->map(fn ($s) => [...$s->toArray(), 'total' => app(AudienceService::class)->query($organisation->id, 'segment', $s->id)->count(), 'eligible' => app(AudienceService::class)->query($organisation->id, 'segment', $s->id, true)->count()]), 'canManage' => Gate::allows('manageCampaigns', $organisation)]);
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
        $list->update($r->validate(['name' => 'required|string|max:120', 'description' => 'nullable|string|max:2000']));
        AuditLog::record('list.updated', $organisation->id, ['list_id' => $list->id]);

        return back()->with('success', 'List updated.');
    }

    public function show(Request $r, Organisation $organisation, MailingList $list)
    {
        Gate::authorize('view', $organisation);
        abort_unless($list->organisation_id === $organisation->id, 404);
        $search = $r->validate(['search' => 'nullable|string|max:120'])['search'] ?? '';

        return Inertia::render('list-detail', ['company' => $organisation, 'list' => $list, 'contacts' => Contact::where('organisation_id', $organisation->id)->where('email', 'like', "%$search%")->withExists(['lists as member' => fn ($q) => $q->where('mailing_lists.id', $list->id)])->orderBy('email')->paginate(25)->withQueryString(), 'eligible' => app(AudienceService::class)->query($organisation->id, 'list', $list->id, true)->count(), 'canManage' => Gate::allows('manageCampaigns', $organisation), 'history' => AuditLog::where('organisation_id', $organisation->id)->where('metadata->list_id', $list->id)->latest('id')->limit(20)->get()]);
    }

    public function membership(Request $r, Organisation $organisation, MailingList $list)
    {
        Gate::authorize('manageCampaigns', $organisation);
        abort_unless($list->organisation_id === $organisation->id, 404);
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

        return response()->json(['total' => app(AudienceService::class)->query($organisation->id, 'segment', $segment->id)->count(), 'eligible' => app(AudienceService::class)->query($organisation->id,'segment',$segment->id,true)->count(), 'sample' => app(AudienceService::class)->query($organisation->id,'segment',$segment->id)->limit(20)->get(['id', 'email', 'eligibility'])]);
    }
}
