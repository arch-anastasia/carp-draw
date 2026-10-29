<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HTML Whitespace Collapsing
    |--------------------------------------------------------------------------
    |
    | Blade leaves the indentation of every nested component in the rendered
    | HTML. On the upstream HA home page that was 883 KB of a 1,513 KB document, and the
    | browser tokenises all of it before it can build the DOM - the cost lands
    | on parse time, not transfer, since gzip compresses indentation almost
    | perfectly.
    |
    | App\Http\Middleware\CollapseHtmlWhitespace collapses newline-bearing
    | runs to a single space, skipping <pre>, <textarea>, <script> and <style>
    | where spacing is load bearing. Turn it off to read rendered source, or
    | if a page ever turns out to depend on Blade's indentation.
    |
    */

    'collapse_html_whitespace' => (bool) env('COLLAPSE_HTML_WHITESPACE', true),

];
