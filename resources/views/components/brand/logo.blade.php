@props([
    'href' => '/',
    'compact' => false,
    /** Optional sizing context modifier, e.g. "header" - sizing itself lives in CSS. */
    'width' => null,
])

<a
    {{ $attributes->merge(['class' => trim('brand-logo' . ($compact ? ' brand-logo--compact' : '') . ($width ? ' brand-logo--' . $width : ''))]) }}
    href="{{ $href }}"
    aria-label="CarpDraw home"
>
    <img src="/brand/carpdraw-logo-orange.png" alt="CarpDraw" width="1396" height="257">
</a>
