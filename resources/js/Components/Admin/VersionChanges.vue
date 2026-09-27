<script setup lang="ts">
/**
 * Everything one version changes: the scalar fields first, then the body.
 *
 * Fields before body deliberately. A title or a dateline change is one line and
 * easy to miss under two hundred lines of prose diff, and it is often the
 * change that matters most - a rewritten headline is a different article to a
 * search engine.
 */
import BodyDiff from '@/Components/Admin/BodyDiff.vue'
import type { BodyDiffData } from '@/Components/Admin/BodyDiff.vue'

export interface FieldChange {
    key: string
    label: string
    before: string
    after: string
}

export interface VersionDiff {
    body: BodyDiffData
    fields: FieldChange[]
    hasChanges: boolean
}

defineProps<{ diff: VersionDiff }>()
</script>

<template>
    <div class="space-y-8">
        <p
            v-if="!diff.hasChanges"
            class="rounded-box border border-base-300 bg-base-200/40 px-4 py-3 text-sm text-base-content/60"
        >
            This version is identical to the live article. Nothing would change.
        </p>

        <section v-if="diff.fields.length" class="space-y-3">
            <p class="eyebrow text-base-content/45">Fields</p>

            <div class="overflow-hidden rounded-box border border-base-300">
                <div
                    v-for="(field, index) in diff.fields"
                    :key="field.key"
                    class="grid gap-x-4 gap-y-1 px-4 py-3 sm:grid-cols-[9rem_1fr_1fr]"
                    :class="index > 0 ? 'border-t border-base-300' : ''"
                >
                    <p class="text-[13px] font-medium text-base-content/70">{{ field.label }}</p>

                    <p class="rounded-[4px] bg-error/10 px-2 py-1 text-[13px] text-base-content/70">
                        <span v-if="field.before">{{ field.before }}</span>
                        <span v-else class="italic text-base-content/40">empty</span>
                    </p>

                    <p class="rounded-[4px] bg-success/10 px-2 py-1 text-[13px] text-base-content/70">
                        <span v-if="field.after">{{ field.after }}</span>
                        <span v-else class="italic text-base-content/40">empty</span>
                    </p>
                </div>
            </div>
        </section>

        <section class="space-y-3">
            <p class="eyebrow text-base-content/45">Body</p>
            <BodyDiff :diff="diff.body" />
        </section>
    </div>
</template>
