<?php

namespace App\Services;

use App\Exceptions\PilotSubmissionBlocked;
use Illuminate\Support\Facades\DB;

class SendGridPilotGuard
{
    public function enabled(): bool
    {
        return (bool) config('dispatch.pilot.enabled');
    }

    public function configurationErrors(?int $organisationId): array
    {
        if (! $this->enabled()) {
            return [];
        }
        $errors = [];
        $emails = config('dispatch.pilot.recipients', []);
        $organisations = config('dispatch.pilot.organisation_ids', []);
        if (! $emails || collect($emails)->contains(fn ($email) => ! filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $errors[] = 'Pilot sending requires a valid, explicit test-recipient allowlist.';
        }
        if (! $organisationId || ! $organisations || collect($organisations)->contains(fn ($id) => ! ctype_digit((string) $id) || (int) $id < 1) || ! in_array((string) $organisationId, array_map('strval', $organisations), true)) {
            $errors[] = 'This company has not been authorised for the restricted pilot.';
        }
        $limit = (int) config('dispatch.pilot.daily_limit');
        if ($limit < 1 || $limit > 100) {
            $errors[] = 'Pilot daily limit must be between 1 and 100; the recommended limit is 25.';
        }

        return $errors;
    }

    public function recipientAllowed(string $email): bool
    {
        return ! $this->enabled() || in_array(strtolower(trim($email)), array_map(fn ($v) => strtolower(trim($v)), config('dispatch.pilot.recipients', [])), true);
    }

    public function remaining(): int
    {
        return max(0, (int) config('dispatch.pilot.daily_limit') - (int) DB::table('sendgrid_pilot_budgets')->where('day', now('UTC')->toDateString())->value('reserved'));
    }

    public function blockReason(string $email, int $organisationId): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        return match (true) {
            $this->configurationErrors($organisationId) !== [] => 'pilot_configuration_invalid',
            ! $this->recipientAllowed($email) => 'pilot_recipient_not_allowed',
            $this->remaining() < 1 => 'pilot_daily_limit_reached',
            default => null,
        };
    }

    /** Commits before HTTP. Errors/timeouts consume capacity conservatively; no automatic refund. */
    public function reserve(string $email, string $correlation, ?int $organisationId): void
    {
        if (! $this->enabled()) {
            return;
        }
        if ($this->configurationErrors($organisationId) || ! $this->recipientAllowed($email)) {
            throw new PilotSubmissionBlocked('pilot_scope_blocked');
        }
        $day = now('UTC')->toDateString();
        // Complete row creation before acquiring the exclusive reservation lock. An
        // ignored insert inside the transaction retains shared locks and deadlocks
        // competing first-use workers when they all upgrade to FOR UPDATE.
        DB::table('sendgrid_pilot_budgets')->insertOrIgnore(['day' => $day, 'reserved' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::transaction(function () use ($correlation, $day) {
            $budget = DB::table('sendgrid_pilot_budgets')->where('day', $day)->lockForUpdate()->first();
            $hash = hash('sha256', $correlation);
            if (DB::table('sendgrid_pilot_reservations')->where('correlation_hash', $hash)->exists()) {
                throw new PilotSubmissionBlocked('pilot_attempt_already_reserved');
            }
            if ($budget->reserved >= (int) config('dispatch.pilot.daily_limit')) {
                throw new PilotSubmissionBlocked('pilot_daily_limit_reached');
            }
            DB::table('sendgrid_pilot_reservations')->insert(['budget_id' => $budget->id, 'correlation_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('sendgrid_pilot_budgets')->where('id', $budget->id)->increment('reserved', 1, ['updated_at' => now()]);
        }, 3);
    }
}
