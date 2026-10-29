<script setup lang="ts">
withDefaults(
    defineProps<{
        href?: string
        compact?: boolean
    }>(),
    {
        href: '/',
        compact: false,
    }
)
</script>

<template>
    <!-- Plain <a>, not <Link>: the logo points at the Blade marketing home,
         and an Inertia visit to a non-Inertia page opens the invalid-response
         modal instead of navigating. -->
    <a
        :class="['brand-logo', { 'brand-logo--compact': compact }]"
        :href="href"
        aria-label="CarpDraw home"
    >
        <span class="brand-wordmark"><img src="/brand/carpdraw-cube-logo.jpg" alt="CarpDraw" width="1280" height="476" /></span>
    </a>
</template>
