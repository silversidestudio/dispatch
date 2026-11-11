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

    public function index(Organisation $organisation)
    {
        Gate::authorize('view', $organisation);

        return Inertia::render('campaigns', ['company' => $organisation, 'campaigns' => Campaign::where('organisation_id', $organisation->id)->latest()->paginate(15), 'canManage' => Gate::allows('manageCampaigns', $organisation)]);
    }

    public function create(Organisation $organisation)
    {
        Gate::authorize('manageCampaigns', $organisation);

        return $this->form($organisation, null);
    }

    private function form(Organisation $o, ?Campaign $c)
    {
        return Inertia::render('campaign-editor', ['company' => $o, 'campaign' => $c, 'defaultEmail' => app(EmailStarterTemplates::class)->basic($o->email_branding ?? []), 'identities' => SendingIdentity::where('organisation_id', $o->id)->get(), 'lists' => MailingList::where('organisation_id', $o->id)->get(['id', 'name']), 'segments' => Segment::where('organisation_id', $o->id)->get(['id', 'name']), 'templates' => EmailTemplate::where('organisation_id', $o->id)->get(), 'canManage' => Gate::allows('manageCampaigns', $o)]);
    }

    public function edit(Organisation $organisation, Campaign $campaign)
    {
        $this->scope($organisation, $campaign, 'manageCampaigns');
        app(CampaignService::class)->requireState($campaign, ['draft']);
        abort_if($campaign->snapshot, 422);

        return $this->form($organisation, $campaign);
    }

    public function save(Request $r, Organisation $organisation, ?Campaign $campaign = null)
    {
        Gate::authorize('manageCampaigns', $organisation);
        if ($campaign) {
            $this->scope($organisation, $campaign, 'manageCampaigns');
        }$d = $r->validate(['delivery_mode' => 'sometimes|in:simulation,sendgrid_sandbox,sendgrid_live', 'open_tracking' => 'sometimes|boolean', 'click_tracking' => 'sometimes|boolean', 'name' => 'required|string|max:120', 'subject' => 'required|string|max:255', 'preview_text' => 'nullable|string|max:255', 'sending_identity_id' => ['required', 'integer', Rule::exists('sending_identities', 'id')->where('organisation_id', $organisation->id)], 'reply_to' => 'nullable|email:rfc|max:254', 'audience_type' => 'required|in:list,segment', 'audience_id' => 'required|integer', 'html' => 'required|string|max:200000', 'text_body' => 'required|string|max:100000']);
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

    public function show(Organisation $organisation, Campaign $campaign)
    {
        $this->scope($organisation, $campaign);
        $stats = $this->stats($campaign);
        if (($campaign->snapshot['delivery_mode'] ?? $campaign->delivery_mode) !== 'simulation') {
            $stats = ['original_audience' => $campaign->audience_count, 'eligible_recipients' => $campaign->recipients()->count(), 'excluded' => $campaign->excluded_count, 'provider_attempts' => $stats['simulated_attempts'], 'api_acceptances' => $campaign->recipients()->where('status', 'accepted')->count(), 'uncertain' => $campaign->recipients()->where('status', 'uncertain')->count(), 'sandbox_validated' => $campaign->recipients()->where('status', 'sandbox_validated')->count()];
        }

        return Inertia::render('campaign-detail', ['company' => $organisation, 'campaign' => $campaign, 'preflight' => app(CampaignService::class)->preflight($campaign), 'stats' => $stats, 'recipients' => $campaign->recipients()->orderBy('id')->paginate(25), 'events' => DeliveryEvent::where('organisation_id', $organisation->id)->where('campaign_id', $campaign->id)->latest('id')->limit(30)->get(), 'history' => AuditLog::where('organisation_id', $organisation->id)->where('metadata->campaign_id', $campaign->id)->orderBy('id')->get(), 'canManage' => Gate::allows('manageCampaigns', $organisation)]);
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
        app(CampaignService::class)->ready($campaign);

        return back()->with('success', 'Campaign is ready for the selected delivery mode.');
    }

    public function schedule(Request $r, Organisation $organisation, Campaign $campaign)
    {
        $this->scope($organisation, $campaign, 'manageCampaigns');
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
        $d = $r->validate(['email' => 'required|email:rfc|max:254', 'mode' => 'required|in:simulation,sendgrid_sandbox', 'confirmation' => 'required|in:PREPARE']);
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
        $this->scope($organisation, $campaign);
        abort_unless($recipient->campaign_id === $campaign->id && $recipient->organisation_id === $organisation->id, 404);
        $c = Contact::where('organisation_id', $organisation->id)->where('email', $recipient->email)->findOrFail($recipient->contact_id);

        return response()->json(['url' => app(UnsubscribeService::class)->url($c, $recipient)]);
    }

    public function reporting(Organisation $organisation)
    {
        Gate::authorize('view', $organisation);
        $campaigns = Campaign::where('organisation_id', $organisation->id)->whereNotNull('snapshot')->latest()->limit(30)->get();

        return Inertia::render('reports', ['company' => $organisation, 'reports' => $campaigns->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'status' => $c->status, 'mode' => $c->snapshot['delivery_mode'] ?? 'simulation', 'date' => $c->created_at, ...$this->stats($c)])]);
    }
}
