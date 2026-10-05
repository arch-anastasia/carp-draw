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
    <span class="brand-wordmark"><img src="/brand/carpdraw-cube-logo.jpg" alt="CarpDraw" width="1280" height="476"></span>
</a>
