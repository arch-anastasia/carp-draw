<script setup lang="ts">
/**
 * What one version changed, on its own screen.
 *
 * The same view the editor shows inline, reached from the article's history -
 * which is the only way to read an applied version, since there is nothing left
 * to edit. For those the comparison is against the stored snapshot of what the
 * article said at the moment this wording replaced it, not against today's
 * live text: the question is "what did this change", and answering it with a
 * comparison to two versions later would be a different question.
 */
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import VersionChanges from '@/Components/Admin/VersionChanges.vue'
import type { VersionDiff } from '@/Components/Admin/VersionChanges.vue'

defineProps<{
    post: { id: string; title: string; url: string }
    revision: {
        id: string
        note: string | null
        is_pending: boolean
        created_at: string | null
        applied_at: string | null
        applied_by: string | null
    }
    diff: VersionDiff
}>()
</script>

<template>
    <AdminLayout title="Version changes">
        <div class="mb-8">
            <Link
                :href="`/admin/posts/${post.id}/edit`"
                class="text-sm text-base-content/60 underline underline-offset-4 transition-colors hover:text-base-content"
            >
                &larr; {{ post.title }}
            </Link>

            <div class="mt-3 flex flex-wrap items-center gap-3">
                <h1 class="heading-section text-base-content">Version changes</h1>
                <span
                    class="rounded-[4px] px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.12em]"
                    :class="revision.is_pending
                        ? 'bg-base-content text-base-100'
                        : 'bg-base-300 text-base-content/70'"
                >
                    {{ revision.is_pending ? 'Pending approval' : 'Applied' }}
                </span>
            </div>

            <p class="mt-2 text-[13px] text-base-content/50">
                <template v-if="revision.applied_at">
                    Replaced the live wording on {{ revision.applied_at }}<template v-if="revision.applied_by"> by {{ revision.applied_by }}</template>.
                </template>
                <template v-else>
                    Started {{ revision.created_at }}. Compared against the live article as it stands now.
                </template>
            </p>

            <p v-if="revision.note" class="mt-3 rounded-box border border-base-300 bg-base-200/40 px-4 py-3 text-sm text-base-content/70">
                {{ revision.note }}
            </p>
        </div>

        <div class="mb-8 flex flex-wrap items-center gap-4">
            <Link
                v-if="revision.is_pending"
                :href="`/admin/posts/${post.id}/versions/${revision.id}/edit`"
                class="rounded-btn bg-primary px-5 py-2.5 text-sm font-medium text-primary-content transition-opacity hover:opacity-85"
            >
                Edit this version
            </Link>

            <a
                :href="post.url"
                target="_blank"
                rel="noopener"
                class="text-sm text-base-content/60 underline underline-offset-4 transition-colors hover:text-base-content"
            >
                Live article &rarr;
            </a>
        </div>

        <VersionChanges :diff="diff" />
    </AdminLayout>
</template>
