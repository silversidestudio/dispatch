<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <meta name="application-name" content="{{ config('app.name') }}">
        <meta name="theme-color" content="#ffffff" data-workspace-theme-color>
        <link rel="icon" type="image/svg+xml" href="/favicon-light.svg" data-theme-favicon>
        <link rel="icon" type="image/png" sizes="32x32" href="/app-icon-32.png">
        <link rel="manifest" href="/site.webmanifest">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon-180x180.png">
        <link rel="apple-touch-icon" sizes="167x167" href="/apple-touch-icon-167x167.png">
        <link rel="apple-touch-icon" sizes="152x152" href="/apple-touch-icon-152x152.png">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
        <title inertia>{{ config('app.name', 'Laravel') }}</title>




        @routes
        @viteReactRefresh
        @vite('resources/js/app.tsx')
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
