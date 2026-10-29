@props(['posts' => []])
@php
    $items = [];
    foreach ($posts as $post) {
        $items[] = [
            '@type' => 'ListItem', 'position' => count($items) + 1,
            'url' => $post->url(), 'name' => html_entity_decode($post->title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ];
    }
    \Artesaos\SEOTools\Facades\JsonLdMulti::addValue('mainEntity', [
        '@type' => 'ItemList', 'numberOfItems' => count($items),
        'itemListElement' => $items,
    ]);
@endphp
