@php
    $canonical = app(\App\Services\CanonicalUrl::class);

    $node = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        '@id' => $canonical->schemaId('website'),
        'url' => $canonical->origin().'/',
        'name' => config('branding.name'),
        'description' => config('branding.tagline'),
        'publisher' => ['@id' => $canonical->schemaId('organization')],
        'inLanguage' => 'en',
    ];
@endphp
<script type="application/ld+json">{!! json_encode($node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
