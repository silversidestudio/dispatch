<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\EligibilityRecord;
use App\Models\ImportRow;
use App\Models\Organisation;
use App\Services\EligibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContactPrivacyController extends Controller
{
    public function export(Organisation $organisation, Contact $contact)
    {
        Gate::authorize('update', $organisation);
        abort_unless($contact->organisation_id === $organisation->id, 404);
        AuditLog::record('contact.exported', $organisation->id, ['contact_id' => $contact->id]);

        return response()->json(['contact' => $contact, 'eligibility' => EligibilityRecord::where('organisation_id', $organisation->id)->where('contact_id', $contact->id)->get(), 'recipient_snapshots' => CampaignRecipient::where('organisation_id', $organisation->id)->where('contact_id', $contact->id)->get(['id', 'campaign_id', 'email', 'personalisation', 'status'])])->header('Content-Disposition', 'attachment; filename="contact-data.json"')->header('Cache-Control', 'private, no-store');
    }

    public function erase(Request $r, Organisation $organisation, Contact $contact)
    {
        Gate::authorize('update', $organisation);
        abort_unless($contact->organisation_id === $organisation->id, 404);
        $r->validate(['confirmation' => 'required|in:ERASE']);
        DB::transaction(function () use ($organisation, $contact) {
            Organisation::whereKey($organisation->id)->lockForUpdate()->firstOrFail();
            $active = Campaign::where('organisation_id', $organisation->id)->whereIn('status', ['scheduled', 'processing', 'paused'])->whereIn('id', CampaignRecipient::where('contact_id', $contact->id)->select('campaign_id'))->exists();
            if ($active || ContactImport::where('organisation_id', $organisation->id)->whereIn('status', ['queued', 'processing'])->exists()) {
                throw ValidationException::withMessages(['privacy' => 'Cancel active campaigns and finish queued imports before erasure.']);
            }
            $c = Contact::whereKey($contact->id)->lockForUpdate()->firstOrFail();
            $old = $c->email;
            app(EligibilityService::class)->suppress($c, 'administratively_blocked', 'Minimal suppression retained after approved contact erasure.');
            $erased = 'erased-'.$c->id.'@deleted.invalid';
            $c->update(['email' => $erased, 'first_name' => null, 'last_name' => null, 'company_name' => null, 'telephone' => null, 'source' => 'Erased', 'attributes' => null, 'status' => 'archived', 'eligibility' => 'administratively_blocked']);
            CampaignRecipient::where('organisation_id', $organisation->id)->where('contact_id', $c->id)->update(['email' => $erased, 'personalisation' => json_encode([])]);
            EligibilityRecord::where('organisation_id', $organisation->id)->where('contact_id', $c->id)->update(['evidence' => 'Personal evidence erased on approved request.', 'method' => 'Erased', 'policy_version' => null, 'metadata' => null]);
            ImportRow::whereIn('contact_import_id', ContactImport::where('organisation_id', $organisation->id)->select('id'))->where('email', $old)->update(['email' => null]);
            ContactImport::where('organisation_id', $organisation->id)->orderBy('id')->chunkById(50, function ($imports) use ($old) {
                foreach ($imports as $i) {
                    if (Storage::exists($i->path) && str_contains(strtolower(Storage::get($i->path)), strtolower($old))) {
                        Storage::delete($i->path);
                        $i->update(['status' => 'erased', 'failure' => 'Raw upload removed for a data-erasure request. Aggregate import results retained.']);
                    }
                }
            });
            AuditLog::record('contact.erased', $organisation->id, ['contact_id' => $c->id, 'snapshot_redaction' => 'Explicit privacy exception to content immutability; suppression retained.']);
        });

        return back()->with('success','Personal contact data erased. Minimal suppression and audit evidence retained.');
    }
}
