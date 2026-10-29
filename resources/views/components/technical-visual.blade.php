@props([
    'label' => 'CONCEPT / 01',
])

<div
    {{ $attributes->merge(['class' => 'technical-visual']) }}
    role="img"
    aria-label="Neutral technical drawing placeholder"
>
    <span class="tech-line one"></span>
    <span class="tech-line two"></span>
    <span class="tech-line three"></span>
    <span class="tech-dot one"></span>
    <span class="tech-dot two"></span>
</div>
