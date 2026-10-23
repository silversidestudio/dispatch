<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        abort_if(! $request->is('unsubscribe/*') && $request->user() && $request->user()->status !== 'active', 403, 'This account is suspended. Contact your administrator.');

        return $next($request);
    }
}
