<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">

        <title inertia>{{ config('app.name') }}</title>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon.png" type="image/png" sizes="128x128">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="preload" href="/fonts/InterVariable.woff2" as="font" type="font/woff2" crossorigin>

        {{-- json: an inert data block instead of an inline script — the
             production CSP is script-src 'self', which would block Ziggy's
             executable output. ziggy-js bootstraps from #ziggy-routes-json. --}}
        @routes(json: true)
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
