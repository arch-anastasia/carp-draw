@props([
    /** Hide the public header and footer - the 404 page renders without chrome. */
    'hideChrome' => false,
])

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {!! SEO::generate() !!}

        <x-schema.website />
        <x-schema.organization />

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon.png" type="image/png" sizes="128x128">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- The primary text face, preloaded: it paints every headline, and
             without the hint the browser only discovers it after CSS. --}}
        <link rel="preload" href="/fonts/InterVariable.woff2" as="font" type="font/woff2" crossorigin>

        @vite(['resources/css/marketing.css', 'resources/js/marketing.ts'])
    </head>
    <body class="antialiased">
        <a class="skip-link" href="#main-content">Skip to content</a>

        @unless ($hideChrome)
            <x-marketing.header />
        @endunless

        {{ $slot }}

        @unless ($hideChrome)
            <x-marketing.footer />
        @endunless

        <x-marketing.auth-modal />
    </body>
</html>
