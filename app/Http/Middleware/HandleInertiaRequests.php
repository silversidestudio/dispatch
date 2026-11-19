<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');
        $user = $request->user();
        $hasNotificationsTable = Schema::hasTable('notifications');
        $currentOrganisationId = $request->session()->get('organisation_id');
        $workspaceBranding = null;
        if ($user && $currentOrganisationId && Schema::hasColumn('organisations', 'workspace_branding')) {
            $workspaceBranding = $user->organisations()
                ->whereKey($currentOrganisationId)
                ->where('organisations.status', 'active')
                ->first(['organisations.id', 'organisations.workspace_branding'])
                ?->workspace_branding;
        }
        $notifications = $user && $hasNotificationsTable
            ? $user->notifications()->latest('created_at')->limit(6)->get(['id', 'data', 'read_at', 'created_at'])
            : collect();

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'organisations' => $request->user()?->organisations()->where('status', 'active')->get()->map(fn ($o) => ['id' => $o->id, 'name' => $o->name]) ?? [],
            'currentOrganisationId' => $currentOrganisationId,
            'workspaceBranding' => $workspaceBranding,
            'flash' => ['success' => $request->session()->get('success'), 'invitation_url' => $request->session()->get('invitation_url')],
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user,
            ],
            'notifications' => [
                'unread_count' => $user && $hasNotificationsTable ? $user->unreadNotifications()->count() : 0,
                'items' => $notifications?->map(fn ($notification) => [
                    'id' => $notification->id,
                    'title' => $notification->data['title'] ?? 'Update',
                    'body' => $notification->data['body'] ?? '',
                    'read' => $notification->read_at !== null,
                    'created_at' => $notification->created_at?->toISOString(),
                ])->values() ?? [],
            ],
        ]);
    }
}
