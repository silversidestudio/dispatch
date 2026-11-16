<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\ContactImport;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class InAppNotificationService
{
    public function campaignFinished(Campaign $campaign): void
    {
        $campaign->loadMissing('recipients');
        $mode = $campaign->snapshot['delivery_mode'] ?? 'simulation';
        $accepted = $campaign->recipients()->whereIn('status', ['accepted', 'sandbox_validated'])->count();
        $failed = $campaign->recipients()->where('status', 'failed')->count();

        if ($campaign->status === 'failed') {
            $title = 'Campaign stopped';
            $body = 'The campaign stopped after an error. Review its activity before taking further action.';
        } elseif ($mode === 'simulation') {
            $title = 'Simulation complete';
            $body = config('app.name').' finished the simulation. '.$accepted." simulated acceptances; $failed failed. No email was sent.";
        } elseif ($mode === 'sendgrid_sandbox') {
            $title = 'SendGrid test complete';
            $body = "SendGrid checked $accepted messages. No email was delivered to recipients.";
        } else {
            $title = 'Campaign processing complete';
            $body = "$accepted messages accepted by SendGrid; $failed submissions failed. Delivery feedback may continue to update the results.";
        }

        $this->notifyOrganisationUsers(
            organisationId: $campaign->organisation_id,
            userIds: $campaign->snapshot['notification_user_ids'] ?? [$campaign->created_by],
            eventKey: "campaign.{$campaign->status}:{$campaign->id}",
            type: 'campaign',
            title: $title,
            body: $body,
            targetId: $campaign->id,
        );
    }

    public function importFinished(ContactImport $import): void
    {
        $title = $import->status === 'failed' ? 'Contact import stopped' : 'Contact import complete';
        $body = $import->status === 'failed'
            ? 'The import stopped before all rows were processed. Review the import for details.'
            : "{$import->successful} contacts imported; {$import->rejected} rows rejected. Review the results and error report.";

        $this->notifyOrganisationUsers(
            organisationId: $import->organisation_id,
            userIds: [$import->user_id],
            eventKey: "contact-import.{$import->status}:{$import->id}",
            type: 'contact_import',
            title: $title,
            body: $body,
            targetId: $import->id,
        );
    }

    private function notifyOrganisationUsers(
        int $organisationId,
        array $userIds,
        string $eventKey,
        string $type,
        string $title,
        string $body,
        int $targetId,
    ): void {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $organisation = Organisation::query()->whereKey($organisationId)->where('status', 'active')->first();
        if (! $organisation) {
            return;
        }

        $members = $organisation->members()->whereIn('users.id', array_filter($userIds))->get(['users.id']);
        foreach ($members as $member) {
            DB::table('notifications')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'type' => $type,
                'notifiable_type' => User::class,
                'notifiable_id' => $member->id,
                'event_key' => $eventKey,
                'data' => json_encode([
                    'type' => $type,
                    'title' => $title,
                    'body' => $body,
                    'organisation_id' => $organisationId,
                    'target_id' => $targetId,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        }
    }
}
