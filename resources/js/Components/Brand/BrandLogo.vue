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
        aria-label="CarpDraw AI home"
    >
        <img src="/brand/carpdraw-logo-orange.png" alt="CarpDraw AI" width="1396" height="257" />
    </a>
</template>
