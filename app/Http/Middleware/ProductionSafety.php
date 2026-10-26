<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ProductionSafety
{
    public function handle(Request $r, Closure $next)
    {
        if (app()->environment('production')) {
            abort_if(config('app.debug') || ! str_starts_with(config('app.url'), 'https://') || ! config('session.secure'), 503, 'Production security configuration is incomplete.');
            abort_unless($r->isSecure(), 400, 'HTTPS is required.');
        }
        $response = $next($r);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        if ($r->user()) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }
        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
