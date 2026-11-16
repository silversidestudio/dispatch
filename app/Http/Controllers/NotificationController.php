<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\ContactImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    public function open(Request $request, string $notification)
    {
        abort_unless(Schema::hasTable('notifications'), 404);

        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $data = $item->data;
        $organisation = $request->user()->organisations()
            ->where('organisations.status', 'active')
            ->whereKey((int) ($data['organisation_id'] ?? 0))
            ->firstOrFail();

        $target = match ($data['type'] ?? null) {
            'campaign' => Campaign::query()
                ->where('organisation_id', $organisation->id)
                ->whereKey((int) ($data['target_id'] ?? 0))
                ->firstOrFail()
                ->id,
            'contact_import' => ContactImport::query()
                ->where('organisation_id', $organisation->id)
                ->whereKey((int) ($data['target_id'] ?? 0))
                ->firstOrFail()
                ->id,
            default => abort(404),
        };

        $item->markAsRead();
        $request->session()->put('organisation_id', $organisation->id);

        return $data['type'] === 'campaign'
            ? redirect("/organisations/{$organisation->id}/campaigns/{$target}/analytics")
            : redirect("/organisations/{$organisation->id}/imports/{$target}");
    }

    public function readAll(Request $request)
    {
        abort_unless(Schema::hasTable('notifications'), 404);

        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
