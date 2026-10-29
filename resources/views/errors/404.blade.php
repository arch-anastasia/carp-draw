<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Page not found | CarpDraw AI</title>
        <meta name="robots" content="noindex">

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon.png" type="image/png" sizes="128x128">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @vite(['resources/css/marketing.css', 'resources/js/marketing.ts'])
    </head>
    <body class="antialiased">
        <a class="skip-link" href="#main-content">Skip to content</a>
        <main id="main-content" class="not-found-page">
            <div class="not-found-inner">
                <x-brand.logo />
                <span class="eyebrow">Error 404</span>
                <h1>This drawing view does not exist.</h1>
                <p>
                    The page may have moved, or the link may be incomplete. Return to the CarpDraw
                    AI landing page or open the workspace.
                </p>
                <div>
                    <a class="secondary-button" href="/">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                        Back home
                    </a>
                    <a class="primary-button" href="/app">Open workspace</a>
                </div>
            </div>
            <x-technical-visual label="404 / NOT FOUND" />
        </main>
    </body>
</html>
