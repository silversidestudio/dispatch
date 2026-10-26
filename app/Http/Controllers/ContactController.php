<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\EligibilityRecord;
use App\Models\Organisation;
use App\Models\Suppression;
use App\Services\EligibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ContactController extends Controller
{
    public function index(Request $r, Organisation $organisation)
    {
        Gate::authorize('view', $organisation);
        $d = $r->validate(['search' => 'nullable|string|max:120', 'status' => 'nullable|in:active,archived', 'eligibility' => 'nullable|string|max:40', 'sort' => 'nullable|in:email,created_at,first_name']);
        $q = Contact::where('organisation_id', $organisation->id);
        if ($s = $d['search'] ?? null) {
            $q->where(fn ($q) => $q->where('email', 'like', "%$s%")->orWhere('first_name', 'like', "%$s%")->orWhere('last_name', 'like', "%$s%"));
        }foreach (['status', 'eligibility'] as $key) {
            if ($v = $d[$key] ?? null) {
                $q->where($key, $v);
            }
        }

        return Inertia::render('contacts', ['company' => $organisation, 'contacts' => $q->orderBy($d['sort'] ?? 'created_at', 'desc')->paginate(20)->withQueryString(), 'filters' => $d, 'canManage' => Gate::allows('manageCampaigns', $organisation), 'stats' => ['total' => Contact::where('organisation_id', $organisation->id)->count(), 'eligible' => app(EligibilityService::class)->query($organisation->id)->count(), 'suppressed' => Suppression::where('organisation_id', $organisation->id)->count()]]);
    }

    private function data(Request $r, Organisation $o, ?Contact $c = null): array
    {
        $r->merge(['email' => strtolower(trim($r->input('email', '')))]);

        return $r->validate(['email' => ['required', 'email:rfc', 'max:254', Rule::unique('contacts')->where('organisation_id', $o->id)->ignore($c?->id)], 'first_name' => 'nullable|string|max:120', 'last_name' => 'nullable|string|max:120', 'company_name' => 'nullable|string|max:255', 'telephone' => 'nullable|string|max:60', 'source' => 'required|string|max:255', 'status' => 'required|in:active,archived', 'attributes' => 'nullable|array|max:20', 'attributes.*' => 'nullable|string|max:255']);
    }

    public function store(Request $r, Organisation $organisation)
    {
        Gate::authorize('manageCampaigns', $organisation);
        $d = $this->data($r, $organisation);
        $c = DB::transaction(function () use ($organisation, $d) {
            $c = Contact::create(['organisation_id' => $organisation->id, ...$d, 'eligibility' => 'unknown']);
            AuditLog::record('contact.created', $organisation->id, ['contact_id' => $c->id]);

            return $c;
        });

        return redirect("/organisations/$organisation->id/contacts/$c->id")->with('success', 'Contact stored. Marketing eligibility is unknown.');
    }

    public function show(Organisation $organisation, Contact $contact)
    {
        Gate::authorize('view', $organisation);
        abort_unless($contact->organisation_id === $organisation->id, 404);

        return Inertia::render('contact-detail', ['company' => $organisation, 'contact' => $contact, 'eligible' => app(EligibilityService::class)->eligible($contact), 'canManage' => Gate::allows('manageCampaigns', $organisation), 'canPrivacy' => Gate::allows('update', $organisation), 'evidence' => EligibilityRecord::where('organisation_id', $organisation->id)->where('contact_id', $contact->id)->latest('id')->get(), 'suppression' => Suppression::where('organisation_id', $organisation->id)->where('email', $contact->email)->first(), 'history' => AuditLog::where('organisation_id', $organisation->id)->where('metadata->contact_id', $contact->id)->latest('id')->limit(40)->get()]);
    }

    public function update(Request $r, Organisation $organisation, Contact $contact)
    {
        Gate::authorize('manageCampaigns', $organisation);
        abort_unless($contact->organisation_id === $organisation->id, 404);
        $d = $this->data($r, $organisation, $contact);
        DB::transaction(function () use ($contact, $d, $organisation) {
            $c = Contact::whereKey($contact->id)->lockForUpdate()->firstOrFail();
            if ($c->email !== $d['email']) {
                if (Suppression::where('organisation_id', $organisation->id)->where('email', $c->email)->exists()) {
                    app(EligibilityService::class)->suppress($c, 'administratively_blocked', 'Email address changed while suppressed.');
                }$d['eligibility'] = 'unknown';
                $oldSuppressed = Suppression::where('organisation_id', $organisation->id)->where('email', $c->email)->exists();
                $c->update($d);
                if ($oldSuppressed) {
                    app(EligibilityService::class)->suppress($c, 'administratively_blocked', 'Suppression retained after address change.');
                }
            } else {
                $c->update($d);
            }AuditLog::record('contact.updated', $organisation->id, ['contact_id' => $c->id]);
        });

        return back()->with('success', 'Contact updated.');
    }

    public function eligibility(Request $r, Organisation $organisation, Contact $contact)
    {
        Gate::authorize('manageCampaigns', $organisation);
        abort_unless($contact->organisation_id === $organisation->id, 404);
        $d = $r->validate(['basis' => 'required|in:unknown,consent,documented_basis', 'evidence' => 'required|string|min:10|max:4000', 'method' => 'required|string|max:255', 'recorded_at' => 'required|date|before_or_equal:now', 'policy_version' => 'nullable|string|max:120']);
        app(EligibilityService::class)->record($contact, $d);

        return back()->with('success', 'Eligibility evidence recorded.');
    }

    public function suppress(Request $r, Organisation $organisation, Contact $contact)
    {
        Gate::authorize('manageCampaigns', $organisation);
        abort_unless($contact->organisation_id === $organisation->id, 404);
        $d = $r->validate(['reason' => 'required|in:unsubscribed,suppressed,hard_bounce,complaint,administratively_blocked', 'evidence' => 'required|string|max:4000']);
        app(EligibilityService::class)->suppress($contact, $d['reason'], $d['evidence']);

        return back()->with('success', 'Suppression recorded.');
    }

    public function bulk(Request $r, Organisation $organisation)
    {
        Gate::authorize('manageCampaigns', $organisation);
        $d = $r->validate(['ids' => 'required|array|min:1|max:200', 'ids.*' => 'integer', 'action' => 'required|in:archive,suppress']);
        $contacts = Contact::where('organisation_id', $organisation->id)->whereIn('id', $d['ids'])->get();
        abort_unless($contacts->count() === count(array_unique($d['ids'])), 404);
        DB::transaction(function () use ($contacts, $d, $organisation) {
            foreach ($contacts as $c) {
                if ($d['action'] === 'archive') {
                    $c->update(['status' => 'archived']);
                } else {
                    app(EligibilityService::class)->suppress($c, 'administratively_blocked', 'Bulk administrative suppression.');
                }AuditLog::record('contact.'.$d['action'], $organisation->id, ['contact_id' => $c->id]);
            }
        });

        return back()->with('success', 'Selected contacts updated.');
    }
}
