@props([
    'headline',
    'description' => null,
    'image' => null,
    'datePublished' => null,
    'dateModified' => null,

    'authors' => null,
    'canonicalUrl' => null,

    'authorName' => null,
    'authorUrl' => null,
])

@php
    $canonical = app(\App\Services\CanonicalUrl::class);

    $organization = [
        '@type' => 'Organization',
        'name' => config('branding.name', config('app.name')),
        'url' => config('app.url'),
        'logo' => [
            '@type' => 'ImageObject',
            'url' => config('app.url').'/favicon.svg',
        ],
    ];

    /*
     * A cover image may be stored as an absolute URL (the ingest API writes
     * one) or as a site-relative path (the launch catalog). Prefixing both
     * produced https://site.com/https://cdn... in the structured data.
     */
    $imageUrl = $image
        ? (str_starts_with($image, 'http') ? $image : rtrim(config('app.url'), '/').'/'.ltrim($image, '/'))
        : null;

    /*
     * Everything the profile page shows, in the schema too: the role, the bio
     * and the writer's own links are the machine-readable half of "who says
     * so", which is the whole question an advice article has to answer.
     */
    $people = collect($authors ?? [])->filter()->map(fn ($person): array => array_filter([
        '@type' => 'Person',
        'name' => $person->name,
        'url' => $canonical->pin($person->authorUrl()),
        'jobTitle' => $person->roleLine(),
        'description' => $person->bio,
        'image' => $canonical->pin($person->avatarUrl()),
        'sameAs' => array_values(array_column($person->profileLinks(), 'url')),
    ]))->values()->all();

    $author = match (true) {
        count($people) === 1 => $people[0],
        count($people) > 1 => $people,
        $authorName !== null => array_filter([
            '@type' => 'Person',
            'name' => $authorName,
            'url' => $canonical->pin($authorUrl),
        ]),
        default => $organization,
    };

    $schema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $headline,
        'description' => $description,
        'image' => $imageUrl,
        'datePublished' => $datePublished,
        'dateModified' => $dateModified ?? $datePublished,
        'inLanguage' => 'en',
        'author' => $author,
        'publisher' => $organization,
        /*
         * The page this article is, named by the same @id the page's own node
         * carries - not url()->current(), which echoes the request host and
         * so disagreed with the canonical beside it.
         */
        'mainEntityOfPage' => [
            '@id' => ($canonicalUrl ?? $canonical->for()).config('seo.schema_ids.webpage'),
        ],
    ]);
@endphp

<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
