<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="30">
    <meta name="robots" content="noindex, nofollow">
    <title>Upgrades in progress · {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light dark; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; display: grid; place-items: center; padding: 24px; background: #f7f7f8; color: #171717; }
        main { width: min(100%, 440px); padding: 36px; border: 1px solid #e4e4e7; border-radius: 20px; background: #fff; box-shadow: 0 12px 36px rgb(0 0 0 / 6%); }
        .brand { margin: 0 0 32px; color: #71717a; font-size: 13px; font-weight: 600; letter-spacing: .04em; }
        .icon { width: 48px; height: 48px; display: grid; place-items: center; border-radius: 14px; background: #f4f4f5; color: #52525b; font-size: 23px; }
        h1 { margin: 22px 0 10px; font-size: clamp(25px, 7vw, 32px); letter-spacing: -.04em; line-height: 1.15; }
        .message { margin: 0; color: #52525b; font-size: 16px; line-height: 1.6; }
        .refresh { margin: 26px 0 0; padding-top: 18px; border-top: 1px solid #e4e4e7; color: #71717a; font-size: 13px; line-height: 1.5; }
        .refresh strong { color: #27272a; }
        @media (prefers-color-scheme: dark) {
            body { background: #09090b; color: #fafafa; }
            main { border-color: #27272a; background: #18181b; box-shadow: 0 12px 36px rgb(0 0 0 / 22%); }
            .brand, .refresh { color: #a1a1aa; }
            .icon { background: #27272a; color: #d4d4d8; }
            .message { color: #d4d4d8; }
            .refresh { border-color: #3f3f46; }
            .refresh strong { color: #f4f4f5; }
        }
    </style>
</head>
<body>
    <main aria-labelledby="page-title">
        <p class="brand">{{ config('app.name') }}</p>
        <div class="icon" aria-hidden="true">↻</div>
        <h1 id="page-title">Upgrades in progress</h1>
        <p class="message">We’re updating the system. Please check back soon.</p>
        <p class="refresh">This page will refresh automatically in <strong>30 seconds</strong>.</p>
    </main>
</body>
</html>
