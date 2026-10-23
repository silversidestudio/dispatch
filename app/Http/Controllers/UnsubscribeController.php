<?php

namespace App\Http\Controllers;

use App\Services\UnsubscribeService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UnsubscribeController extends Controller
{
    public function show(Request $r, string $token)
    {
        try {
            app(UnsubscribeService::class)->contact($token);
        } catch (\Throwable) {
            abort(404);
        }

return Inertia::render('unsubscribe', ['action' => $r->getRequestUri(), 'done' => false]);
    }

    public function store(Request $r, string $token)
    {
        try {
            app(UnsubscribeService::class)->apply($token);
        } catch (DecryptException) {
            abort(404);
        }

return Inertia::render('unsubscribe', ['action' => '', 'done' => true]);
    }
}
