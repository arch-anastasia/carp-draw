<script setup lang="ts">
/**
 * One labeled control in an admin form. Label, optional hint, and the error
 * slot that every field needs and no field should re-implement.
 */
defineProps<{
    label: string
    forId: string
    hint?: string
    error?: string
    optional?: boolean
}>()
</script>

<template>
    <div>
        <label :for="forId" class="block text-sm font-medium text-base-content">
            {{ label }}
            <span v-if="optional" class="ml-1 text-base-content/40">optional</span>
        </label>
        <p v-if="hint" class="mt-1 text-[13px] leading-relaxed text-base-content/50">{{ hint }}</p>
        <div class="mt-2">
            <slot />
        </div>
        <p v-if="error" class="mt-1.5 text-[13px] text-error">{{ error }}</p>
    </div>
</template>
