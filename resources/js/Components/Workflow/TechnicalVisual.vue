<script setup lang="ts">
withDefaults(
    defineProps<{
        label?: string
    }>(),
    {
        label: 'CONCEPT / 01',
    }
)
</script>

<template>
    <div class="technical-visual" role="img" aria-label="Neutral technical drawing placeholder">
        <span class="tech-line one" />
        <span class="tech-line two" />
        <span class="tech-line three" />
        <span class="tech-dot one" />
        <span class="tech-dot two" />
    </div>
</template>
