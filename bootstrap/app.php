<?php

use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ProductionSafety;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $trusted = array_filter(array_map('trim', explode(',', (string) env('DISPATCH_TRUSTED_PROXIES', ''))));
        if ($trusted) {
            $middleware->trustProxies(at: $trusted);
        }
        $middleware->validateCsrfTokens(except: ['webhooks/sendgrid', 'one-click/*']);
        $middleware->web(append: [
            ProductionSafety::class,
            EnsureActiveAccount::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
