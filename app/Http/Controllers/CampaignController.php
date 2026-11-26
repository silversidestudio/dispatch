<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\DeliveryAttempt;
use App\Models\DeliveryEvent;
use App\Models\EmailTemplate;
use App\Models\MailingList;
use App\Models\Organisation;
use App\Models\Segment;
use App\Models\SendingIdentity;
use App\Services\AudienceService;
use App\Services\CampaignService;
use App\Services\EligibilityService;
use App\Services\EmailContentService;
use App\Services\EmailStarterTemplates;
use App\Services\UnsubscribeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CampaignController extends Controller
{
    private function scope(Organisation $o, Campaign $c, string $ability = 'view'): void
    {
        Gate::authorize($ability, $o);
        abort_unless($c->organisation_id === $o->id, 404);
    }

    public function index(Request $request, Organisation $organisation)
    {
        Gate::authorize('view', $organisation);
        $canUseSimulation = (bool) $request->user()->is_platform_admin;
        $view = $request->validate(['view' => 'nullable|in:active,archived,trash'])['view'] ?? 'active';
        $query = Campaign::where('organisation_id', $organisation->id);
        if (! $canUseSimulation) {
            $query->where('delivery_mode', 'sendgrid_live');
        }
        if ($view === 'trash') {
            $query->onlyTrashed();
        } else {
            $query->whereNull('deleted_at');
            $view === 'active' ? $query->whereNull('archived_at') : $query->whereNotNull('archived_at');
        }

        return Inertia::render('campaigns', ['company' => $organisation, 'campaigns' => $query->latest()->paginate(15)->withQueryString(), 'view' => $view, 'canManage' => Gate::allows('manageCampaigns', $organisation), 'canUseSimulation' => $canUseSimulation]);
    }

    public function archive(Organisation $organisation, Campaign $campaign)
    {
        $this->scope($organisation, $campaign, 'manageCampaigns');
        abort_if(in_array($campaign->status, ['scheduled', 'processing', 'paused'], true), 422, 'Cancel or finish this campaign before archiving it.');
        $campaign->update(['archived_at' => now()]);
        AuditLog::record('campaign.archived', $organisation->id, ['campaign_id' => $campaign->id]);

        return back()->with('success', 'Campaign archived. Its delivery history and reports are retained.');
    }

    public function delete(Organisation $organisation, Campaign $campaign)
    {
        $this->scope($organisation, $campaign, 'manageCampaigns');
        abort_unless($campaign->status === 'draft' && ! $campaign->snapshot, 422, 'Only unsent draft campaigns can be deleted. Archive this campaign to keep its history.');
        $campaign->delete();
        AuditLog::record('campaign.deleted', $organisation->id, ['campaign_id' => $campaign->id]);

        return back()->with('success', 'Campaign moved to Recently deleted.');
    }

    public function restore(Organisation $organisation, int $campaign)
    {
        Gate::authorize('manageCampaigns', $organisation);
        $record = Campaign::withTrashed()->where('organisation_id', $organisation->id)->findOrFail($campaign);
        if ($record->trashed()) {
            abort_unless($record->status === 'draft' && ! $record->snapshot, 422, 'Only unsent draft campaigns can be restored from Recently deleted.');
            $record->restore();
            $record->update(['archived_at' => null]);
            AuditLog::record('campaign.restored', $organisation->id, ['campaign_id' => $record->id]);

            return back()->with('success', 'Campaign restored as a draft.');
        }

        if ($record->archived_at) {
            $record->update(['archived_at' => null]);
            AuditLog::record('campaign.unarchived', $organisation->id, ['campaign_id' => $record->id, 'status' => $record->status]);

            return back()->with('success', 'Campaign restored to Active. Its current status and delivery history are unchanged.');
        }

        return back()->with('success', 'Campaign is already active.');
    }

    public function create(Organisation $organisation)
    {
        Gate::authorize('manageCampaigns', $organisation);

        return $this->form($organisation, null);
    }

    private function form(Organisation $o, ?Campaign $c)
    {
        return Inertia::render('campaign-editor', ['company' => $o, 'campaign' => $c, 'defaultEmail' => app(EmailStarterTemplates::class)->basic($o->email_branding ?? []), 'identities' => SendingIdentity::where('organisation_id', $o->id)->whereNull('archived_at')->get(), 'lists' => MailingList::where('organisation_id', $o->id)->whereNull('archived_at')->get(['id', 'name']), 'segments' => Segment::where('organisation_id', $o->id)->get(['id', 'name']), 'templates' => EmailTemplate::where('organisation_id', $o->id)->get(), 'canManage' => Gate::allows('manageCampaigns', $o), 'canUseSimulation' => (bool) auth()->user()->is_platform_admin]);
    }

    public function edit(Organisation $organisation, Campaign $campaign)
    {
        $this->scope($organisation, $campaign, 'manageCampaigns');
        abort_if($campaign->archived_at, 422, 'Restore this campaign before editing it.');
        app(CampaignService::class)->requireState($campaign, ['draft']);
        abort_if($campaign->snapshot, 422);

        return $this->form($organisation, $campaign);
    }

    public function save(Request $r, Organisation $organisation, ?Campaign $campaign = null)
    {
        Gate::authorize('manageCampaigns', $organisation);
        if ($campaign) {
            $this->scope($organisation, $campaign, 'manageCampaigns');
            abort_if($campaign->archived_at, 422, 'Restore this campaign before editing it.');
        }$d = $r->validate(['delivery_mode' => 'sometimes|in:simulation,sendgrid_sandbox,sendgrid_live', 'open_tracking' => 'sometimes|boolean', 'click_tracking' => 'sometimes|boolean', 'name' => 'required|string|max:120', 'subject' => 'required|string|max:255', 'preview_text' => 'nullable|string|max:255', 'sending_identity_id' => ['required', 'integer', Rule::exists('sending_identities', 'id')->where('organisation_id', $organisation->id)->whereNull('archived_at')], 'reply_to' => 'nullable|email:rfc|max:254', 'audience_type' => 'required|in:list,segment', 'audience_id' => 'required|integer', 'html' => 'required|string|max:200000', 'text_body' => 'required|string|max:100000']);
        if (! $r->user()->is_platform_admin) {
            if (isset($d['delivery_mode']) && $d['delivery_mode'] !== 'sendgrid_live') {
                throw ValidationException::withMessages(['delivery_mode' => 'Live SendGrid is the available delivery option for this workspace.']);
            }
            $d['delivery_mode'] = 'sendgrid_live';
        }
        $d += ['delivery_mode' => 'simulation', 'open_tracking' => false, 'click_tracking' => false];
        app(AudienceService::class)->query($organisation->id, $d['audience_type'], $d['audience_id']);
        $d['html'] = app(EmailContentService::class)->clean($d['html']);
        $c = DB::transaction(function () use ($campaign, $organisation, $d, $r) {
            if ($campaign) {
                $c = Campaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
                app(CampaignService::class)->requireState($c, ['draft']);
                abort_if($c->snapshot, 422);
                $c->update($d);
            } else {
                $c = Campaign::create(['organisation_id' => $organisation->id, 'created_by' => $r->user()->id, 'status' => 'draft', ...$d]);
            }AuditLog::record('campaign.saved', $organisation->id, ['campaign_id' => $c->id]);

            return $c;
        });

        return redirect("/organisations/$organisation->id/campaigns/$c->id")->with('success', 'Campaign draft saved.');
    }

    public function show(Request $request, Organisation $organisation, Campaign $campaign)
    {
        $this->scope($organisation, $campaign);
        abort_unless($request->user()->is_platform_admin || $campaign->delivery_mode === 'sendgrid_live', 404);
        $filters = $request->validate([
            'events_per_page' => ['sometimes', 'in:10,50,100'],
            'history_per_page' => ['sometimes', 'in:10,50,100'],
        ]);
        $eventsPerPage = (int) ($filters['events_per_page'] ?? 10);
        $historyPerPage = (int) ($filters['history_per_page'] ?? 10);
        $stats = $this->stats($campaign);
        if (($campaign->snapshot['delivery_mode'] ?? $campaign->delivery_mode) !== 'simulation') {
            $stats = ['original_audience' => $campaign->audience_count, 'eligible_recipients' => $campaign->recipients()->count(), 'excluded' => $campaign->excluded_count, 'provider_attempts' => $stats['simulated_attempts'], 'api_acceptances' => $campaign->recipients()->where('status', 'accepted')->count(), 'uncertain' => $campaign->recipients()->where('status', 'uncertain')->count(), 'sandbox_validated' => $campaign->recipients()->where('status', 'sandbox_validated')->count()];
        }

        return Inertia::render('campaign-detail', [
            'company' => $organisation,
            'campaign' => $campaign,
            'preflight' => app(CampaignService::class)->preflight($campaign),
            'stats' => $stats,
            'recipients' => $campaign->recipients()->orderBy('id')->paginate(25)->withQueryString(),
            'events' => DeliveryEvent::where('organisation_id', $organisation->id)->where('campaign_id', $campaign->id)->latest('id')->paginate($eventsPerPage, ['*'], 'events_page')->withQueryString(),
            'history' => AuditLog::where('organisation_id', $organisation->id)->where('metadata->campaign_id', $campaign->id)->orderByDesc('id')->paginate($historyPerPage, ['*'], 'history_page')->withQueryString(),
            'eventsPerPage' => $eventsPerPage,
            'historyPerPage' => $historyPerPage,
            'canManage' => Gate::allows('manageCampaigns', $organisation),
            'canUseSimulation' => (bool) $request->user()->is_platform_admin,
        ]);
    }

    public function stats(Campaign $c): array
    {
        $r = $c->recipients();
        $total = $r->count();
        $attempts = DeliveryAttempt::where('organisation_id', $c->organisation_id)->whereIn('campaign_recipient_id', $c->recipients()->select('id'));

        return ['audience_at_snapshot' => $c->audience_count, 'suppressed_at_snapshot' => $c->snapshot['suppressed_at_snapshot'] ?? 0, 'other_ineligible_at_snapshot' => $c->snapshot['other_ineligible_at_snapshot'] ?? 0, 'eligible_at_snapshot' => $total, 'excluded_at_snapshot' => $c->excluded_count, 'simulated_attempts' => (clone $attempts)->count(), 'simulated_acceptances' => (clone $r)->where('status', 'accepted')->count(), 'failed' => (clone $r)->where('status', 'failed')->count(), 'suppressed_before_submission' => (clone $r)->where('status', 'suppressed')->count(), 'retries' => (clone $attempts)->where('number', '>', 1)->count(), 'pending_or_deferred' => (clone $r)->whereIn('status', ['pending', 'deferred'])->count()];
    }

    public function ready(Organisation $organisation, Campaign $campaign)
    {
        $this->scope($organisation, $campaign, 'manageCampaigns');
        abort_unless(auth()->user()->is_platform_admin || $campaign->delivery_mode === 'sendgrid_live', 403);
        app(CampaignService::class)->ready($campaign);

        return back()->with('success', 'Campaign is ready for the selected delivery mode.');
    }

    public function schedule(Request $r, Organisation $organisation, Campaign $campaign)
    {
        $this->scope($organisation, $campaign, 'manageCampaigns');
        abort_unless($r->user()->is_platform_admin || $campaign->delivery_mode === 'sendgrid_live', 403);
        $d = $r->validate(['scheduled_at' => 'nullable|date|after_or_equal:now', 'confirmation' => 'nullable|in:SEND']);
        $when = isset($d['scheduled_at']) ? Carbon::parse($d['scheduled_at']) : now();
        app(CampaignService::class)->schedule($campaign, $when, ($d['confirmation'] ?? '') === 'SEND');

        return back()->with('success', 'Immutable recipient and content snapshot created for the selected provider mode.');
    }

    public function pause(Organisation $organisation, Campaign $campaign)
    {
        $this->scope($organisation, $campaign, 'manageCampaigns');
        app(CampaignService::class)->pause($campaign);

        return back()->with('success', 'Campaign paused. In-flight requests cannot be recalled.');
    }

    public function resume(Organisation $organisation, Campaign $campaign)
    {
        $this->scope($organisation, $campaign, 'manageCampaigns');
        app(CampaignService::class)->resume($campaign);

        return back()->with('success', 'Campaign resumed.');
    }

    public function cancel(Organisation $organisation, Campaign $campaign)
    {
        $this->scope($organisation, $campaign, 'manageCampaigns');
        app(CampaignService::class)->cancel($campaign);

        return back()->with('success', 'Campaign cancelled. Recorded attempts remain in history.');
    }

    public function prepareTest(Request $r, Organisation $organisation, Campaign $campaign)
    {
        $this->scope($organisation, $campaign, 'manageCampaigns');
        $testModes = $r->user()->is_platform_admin ? ['simulation', 'sendgrid_sandbox', 'sendgrid_live'] : ['sendgrid_live'];
        $d = $r->validate(['email' => 'required|email:rfc|max:254', 'mode' => ['required', Rule::in($testModes)], 'confirmation' => 'required|in:PREPARE']);
        $contact = Contact::where('organisation_id', $organisation->id)->where('email', strtolower(trim($d['email'])))->first();
        if (! $contact || ! app(EligibilityService::class)->eligible($contact)) {
            throw ValidationException::withMessages(['email' => 'Choose an existing eligible, unsuppressed company contact with permission for this test.']);
        }
        $copy = DB::transaction(function () use ($campaign, $organisation, $contact, $d) {
            $list = MailingList::create(['organisation_id' => $organisation->id, 'name' => 'Test audience '.Str::uuid(), 'description' => 'Explicit single-recipient test audience; membership does not establish eligibility.']);
            $list->contacts()->attach($contact->id);
            $copy = $campaign->replicate(['snapshot', 'scheduled_at', 'completed_at', 'live_authorised_at']);
            $copy->fill(['name' => mb_substr('TEST — '.$campaign->name, 0, 120), 'status' => 'draft', 'audience_type' => 'list', 'audience_id' => $list->id, 'audience_count' => 0, 'excluded_count' => 0, 'delivery_mode' => $d['mode'], 'open_tracking' => false, 'click_tracking' => false, 'created_by' => auth()->id()]);
            $copy->save();
            AuditLog::record('campaign.test_prepared', $organisation->id, ['campaign_id' => $copy->id, 'source_campaign_id' => $campaign->id, 'contact_id' => $contact->id, 'mode' => $d['mode']]);

            return $copy;
        });

        return redirect("/organisations/$organisation->id/campaigns/$copy->id")->with('success', 'Single-recipient test draft prepared. Review, mark ready and schedule explicitly; no request has been submitted.');
    }

    public function duplicate(Organisation $organisation, Campaign $campaign)
    {
        $this->scope($organisation, $campaign, 'manageCampaigns');
        $copy = $campaign->replicate(['snapshot', 'scheduled_at', 'completed_at', 'live_authorised_at']);
        $copy->status = 'draft';
        $copy->name .= ' (copy)';
        $copy->audience_count = 0;
        $copy->excluded_count = 0;
        $copy->created_by = auth()->id();
        $copy->save();
        AuditLog::record('campaign.duplicated', $organisation->id, ['campaign_id' => $copy->id]);

        return redirect("/organisations/$organisation->id/campaigns/$copy->id/edit");
    }

    public function preview(Organisation $organisation, Campaign $campaign)
    {
        $this->scope($organisation, $campaign);
        $s = $campaign->snapshot;
        $identity = SendingIdentity::where('organisation_id', $organisation->id)->findOrFail($campaign->sending_identity_id);
        $values = ['first_name' => 'Alex', 'last_name' => 'Example', 'email' => 'preview@example.test', 'sender_name' => $s['sender_name'] ?? $identity->name, 'sender_address' => $s['sender_address'] ?? $organisation->postal_address ?? 'Address required', 'unsubscribe_url' => '#preview-only'];

        return response(app(EmailContentService::class)->render($s['html'] ?? $campaign->html, $values, $s['preview_text'] ?? $campaign->preview_text ?? ''))->header('Content-Security-Policy', "default-src 'none'; img-src 'self' https:; style-src 'unsafe-inline'; sandbox")->header('Referrer-Policy', 'no-referrer');
    }

    public function audience(Request $r, Organisation $organisation)
    {
        Gate::authorize('view', $organisation);
        $d = $r->validate(['type' => 'required|in:list,segment', 'id' => 'required|integer']);

        return response()->json(['total' => app(AudienceService::class)->query($organisation->id, $d['type'], $d['id'])->count(), 'eligible' => app(AudienceService::class)->query($organisation->id, $d['type'], $d['id'], true)->count()]);
    }

    public function unsubscribe(Organisation $organisation, Campaign $campaign, CampaignRecipient $recipient)
    {
        abort_unless(auth()->user()?->is_platform_admin, 403);
        $this->scope($organisation, $campaign);
        abort_unless($recipient->campaign_id === $campaign->id && $recipient->organisation_id === $organisation->id, 404);
        $c = Contact::where('organisation_id', $organisation->id)->where('email', $recipient->email)->findOrFail($recipient->contact_id);

        return response()->json(['url' => app(UnsubscribeService::class)->url($c, $recipient)]);
    }

    public function reporting(Organisation $organisation)
    {
        Gate::authorize('view', $organisation);
        $canUseSimulation = (bool) auth()->user()->is_platform_admin;
        $campaigns = Campaign::where('organisation_id', $organisation->id)->whereNotNull('snapshot')->when(! $canUseSimulation, fn ($query) => $query->where('delivery_mode', 'sendgrid_live'))->latest()->limit(30)->get();

        return Inertia::render('reports', ['company' => $organisation, 'reports' => $campaigns->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'status' => $c->status, 'mode' => $c->snapshot['delivery_mode'] ?? 'simulation', 'date' => $c->created_at, ...$this->stats($c)]), 'canUseSimulation' => $canUseSimulation]);
    }
}
