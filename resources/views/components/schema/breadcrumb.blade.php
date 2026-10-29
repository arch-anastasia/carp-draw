@props([
    'items' => [],
    'canonicalUrl' => null,
])

@php
    $canonical = app(\App\Services\CanonicalUrl::class);

    /*
     * Names are decoded on the way in. A crumb written "Barbershops &amp;
     * salons" is correct in the markup beside it, but a JSON-LD block is
     * opaque to the HTML parser, so the entity would reach a crawler as its
     * five literal characters.
     */
    $decode = fn (string $value): string => html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $breadcrumbItems = [];

    // Always start with home
    $breadcrumbItems[] = [
        '@type' => 'ListItem',
        'position' => 1,
        'name' => 'Home',
        'item' => $canonical->origin(),
    ];

    // Add additional items
    $position = 2;
    foreach ($items as $item) {
        $breadcrumbItem = [
            '@type' => 'ListItem',
            'position' => $position,
            'name' => $decode($item['name']),
        ];

        // Only add item URL if it's not the current page (last item)
        /*
         * Pinned: callers build these with url(), which resolves against the
         * host the request arrived on, so a trail could name the canonical
         * host for the page and an alias for its parent.
         */
        if (isset($item['url'])) {
            $breadcrumbItem['item'] = $canonical->pin($item['url']);
        }

        $breadcrumbItems[] = $breadcrumbItem;
        $position++;
    }

    /*
     * Identified so the page's own WebPage node can point at this trail by
     * reference rather than the two sitting side by side unrelated.
     */
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        '@id' => ($canonicalUrl ?? $canonical->for()).'#breadcrumb',
        'itemListElement' => $breadcrumbItems,
    ];
@endphp

@php
    /*
     * Register the trail on the page's own node, rather than asking each
     * template to remember a flag saying it has one. This component runs
     * inside the layout's slot, which Blade evaluates before the layout view
     * itself - so the reference is in place by the time SEO::generate()
     * renders the head. The component that knows the trail exists is the one
     * that declares it, and the two can never drift apart.
     */
    \Artesaos\SEOTools\Facades\JsonLdMulti::addValue('breadcrumb', ['@id' => $schema['@id']]);
@endphp

<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
