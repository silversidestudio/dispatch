<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\EligibilityRecord;
use App\Models\Suppression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EligibilityService
{
    public function query(int $organisationId): Builder
    {
        return Contact::where('organisation_id', $organisationId)->where('status', 'active')->whereIn('eligibility', ['consent', 'documented_basis', 'csv_import'])
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('eligibility_records')->whereColumn('eligibility_records.contact_id', 'contacts.id')->whereColumn('eligibility_records.organisation_id', 'contacts.organisation_id')->whereColumn('eligibility_records.basis', 'contacts.eligibility'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('suppressions')->whereColumn('suppressions.organisation_id', 'contacts.organisation_id')->whereColumn('suppressions.email', 'contacts.email'));
    }

    public function eligible(Contact $c): bool
    {
        return $this->query($c->organisation_id)->whereKey($c->id)->exists();
    }

    public function record(Contact $c, array $data): void
    {
        DB::transaction(function () use ($c, $data) {
            $c = Contact::whereKey($c->id)->lockForUpdate()->firstOrFail();
            if (Suppression::where('organisation_id', $c->organisation_id)->where('email', $c->email)->exists()) {
                throw ValidationException::withMessages(['basis' => 'This address is suppressed. Ordinary eligibility edits cannot remove suppression.']);
            }
            EligibilityRecord::create(['organisation_id' => $c->organisation_id, 'contact_id' => $c->id, 'user_id' => auth()->id(), 'basis' => $data['basis'], 'evidence' => $data['evidence'], 'method' => $data['method'], 'policy_version' => $data['policy_version'] ?? null, 'recorded_at' => $data['recorded_at'], 'metadata' => ['source' => $c->source]]);
            $c->update(['eligibility' => $data['basis']]);
            AuditLog::record('contact.eligibility_changed', $c->organisation_id, ['contact_id' => $c->id, 'basis' => $data['basis']]);
        });
    }

    public function suppress(Contact $c, string $reason, string $evidence = ''): void
    {
        DB::transaction(function () use ($c, $reason, $evidence) {
            $c = Contact::whereKey($c->id)->lockForUpdate()->firstOrFail();
            $s = Suppression::firstOrCreate(['organisation_id' => $c->organisation_id, 'email' => $c->email], ['reason' => $reason, 'evidence' => $evidence]);
            $changed = $s->wasRecentlyCreated || $c->eligibility !== $s->reason;
            $c->update(['eligibility' => $s->reason]);
            if ($changed) {
                AuditLog::record('contact.suppressed', $c->organisation_id, ['contact_id' => $c->id, 'reason' => $reason]);
            }
        });
    }
}
