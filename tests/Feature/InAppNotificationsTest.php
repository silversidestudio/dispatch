<?php

use App\Models\User;
use App\Services\CampaignService;
use Illuminate\Support\Facades\DB;

it('creates one recipient-only completion notification and opens the campaign results', function () {
    [$user, $organisation, $campaign] = preparedCampaign();
    $campaign->update([
        'status' => 'processing',
        'snapshot' => ['delivery_mode' => 'sendgrid_live', 'notification_user_ids' => [$user->id]],
    ]);

    app(CampaignService::class)->complete($campaign->id);
    app(CampaignService::class)->complete($campaign->id);

    $notifications = DB::table('notifications')->where('notifiable_id', $user->id)->get();
    expect($notifications)->toHaveCount(1);
    expect(json_decode($notifications->first()->data, true)['title'])->toBe('Campaign processing complete');

    $this->actingAs($user)
        ->post("/notifications/{$notifications->first()->id}/open")
        ->assertRedirect("/organisations/{$organisation->id}/campaigns/{$campaign->id}/analytics");

    expect(DB::table('notifications')->where('id', $notifications->first()->id)->value('read_at'))->not->toBeNull();
});

it('does not let a non-member open another company notification', function () {
    [$owner, $organisation, $campaign] = preparedCampaign();
    $campaign->update([
        'status' => 'processing',
        'snapshot' => ['delivery_mode' => 'simulation', 'notification_user_ids' => [$owner->id]],
    ]);
    app(CampaignService::class)->complete($campaign->id);
    $notificationId = DB::table('notifications')->where('notifiable_id', $owner->id)->value('id');
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)->post("/notifications/$notificationId/open")->assertNotFound();
});
