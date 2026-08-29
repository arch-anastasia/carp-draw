@php
    $canonical = app(\App\Services\CanonicalUrl::class);

    $node = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        '@id' => $canonical->schemaId('organization'),
        'url' => $canonical->origin().'/',
        'name' => config('branding.name'),
        'logo' => [
            '@type' => 'ImageObject',
            'url' => $canonical->origin().'/brand/carpdraw-logo-orange.png',
        ],
        'email' => config('branding.support_email'),
        'contactPoint' => config('branding.support_email') ? [
            '@type' => 'ContactPoint',
            'contactType' => 'customer support',
            'email' => config('branding.support_email'),
            'telephone' => config('branding.support_phone'),
        ] : null,
    ]);
@endphp
<script type="application/ld+json">{!! json_encode($node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
