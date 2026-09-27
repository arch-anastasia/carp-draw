@props([
    'name',
    'description' => null,
    'totalTime' => null,
    'steps' => [],
    'image' => null,
])

@php
    $stepList = [];
    $position = 1;

    foreach ($steps as $step) {
        $entry = [
            '@type' => 'HowToStep',
            'position' => $position,
            'name' => $step['name'],
        ];

        if (! empty($step['text'])) {
            $entry['text'] = html_entity_decode(strip_tags($step['text']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (! empty($step['image'])) {
            $entry['image'] = config('app.url').$step['image'];
        }

        $stepList[] = $entry;
        $position++;
    }

    $schema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'HowTo',
        'name' => $name,
        'description' => $description,
        'totalTime' => $totalTime,
        'image' => $image ? config('app.url').$image : null,
        'step' => $stepList,
    ]);
@endphp

@if (! empty($stepList))
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endif
