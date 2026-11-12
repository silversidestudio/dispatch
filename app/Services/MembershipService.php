<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organisation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MembershipService
{
    public function pending(string $token): ?object
    {
        if (! preg_match('/^[A-Za-z0-9]{64}$/D', $token)) {
            return null;
        }

        return DB::table('organisation_invitations')->join('organisations', 'organisations.id', '=', 'organisation_invitations.organisation_id')
            ->where('organisations.status', 'active')->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())->first(['organisation_invitations.*']);
    }

    public function invite(Organisation $o, string $email, string $role): string
    {
        $email = strtolower(trim($email));
        $token = Str::random(64);
        DB::transaction(function () use ($o, $email, $role, $token) {
            Organisation::whereKey($o->id)->lockForUpdate()->firstOrFail();
            if ($o->members()->where('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => 'Already a member.']);
            }
            DB::table('organisation_invitations')->updateOrInsert(['organisation_id' => $o->id, 'email' => $email], ['role' => $role, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now()]);
            AuditLog::record('invitation.created', $o->id, ['role' => $role]);
        });

        return url('/invitations/'.$token);
    }

    public function change(Organisation $o, int $id, ?string $role): void
    {
        DB::transaction(function () use ($o, $id, $role) {
            Organisation::whereKey($o->id)->lockForUpdate()->firstOrFail();
            $m = $o->members()->where('users.id', $id)->firstOrFail();
            if ($m->pivot->role === 'owner' && $o->members()->wherePivot('role', 'owner')->count() <= 1) {
                throw ValidationException::withMessages(['member' => 'Transfer ownership before removing or changing the last owner.']);
            }
            if ($role) {
                $o->members()->updateExistingPivot($id, ['role' => $role]);
            } else {
                $o->members()->detach($id);
            }
            AuditLog::record($role ? 'membership.role_updated' : 'membership.removed', $o->id, ['member_id' => $id, 'role' => $role]);
        });
    }

    public function transfer(Organisation $o, int $id): void
    {
        DB::transaction(function () use ($o, $id) {
            Organisation::whereKey($o->id)->lockForUpdate()->firstOrFail();
            $new = $o->members()->where('users.id', $id)->where('status', 'active')->firstOrFail();
            $o->members()->wherePivot('role', 'owner')->get()->each(fn ($u) => $o->members()->updateExistingPivot($u->id, ['role' => 'administrator']));
            $o->members()->updateExistingPivot($new->id, ['role' => 'owner']);
            AuditLog::record('ownership.transferred', $o->id, ['new_owner_id' => $id]);
        });
    }
}
