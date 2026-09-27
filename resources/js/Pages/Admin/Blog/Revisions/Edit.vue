<script setup lang="ts">
/**
 * Writing the next version of a live article.
 *
 * The same PostForm the article itself uses, minus the two fields that are the
 * article's identity: a version has no slug and no publish date, because it is
 * a rewrite of an article that already has both.
 *
 * The changes are shown under the form rather than behind a link. The question
 * "what am I actually changing" is the one this screen exists to answer, and an
 * answer one click away is an answer people stop looking at.
 */
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PostForm from '@/Components/Admin/PostForm.vue'
import VersionChanges from '@/Components/Admin/VersionChanges.vue'
import type { PostFormValues } from '@/Components/Admin/PostForm.vue'
import type { VersionDiff } from '@/Components/Admin/VersionChanges.vue'

/**
 * `note` is re-declared as nullable: the column is, and the form's own type
 * treats it as optional-and-present because an input bound to null renders
 * the string "null".
 */
interface Revision extends Omit<PostFormValues, 'note'> {
    id: string
    note: string | null
    is_pending: boolean
    preview_url: string | null
}

const props = defineProps<{
    post: { id: string; title: string; slug: string; url: string }
    revision: Revision
    diff: VersionDiff
    authors: { id: string; name: string; email: string }[]
    categories: { id: string; name: string }[]
    audiences: { value: string; label: string }[]
}>()

/** Null columns become empty strings: an input bound to null renders "null". */
const initial: PostFormValues = {
    title: props.revision.title,
    slug: '',
    authors: props.revision.authors,
    excerpt: props.revision.excerpt ?? '',
    body: props.revision.body,
    cover_image_path: props.revision.cover_image_path ?? '',
    meta_title: props.revision.meta_title ?? '',
    meta_description: props.revision.meta_description ?? '',
    audience: props.revision.audience,
    cta_text: props.revision.cta_text ?? '',
    cta_url: props.revision.cta_url ?? '',
    published_at: '',
    dated_at: props.revision.dated_at ?? '',
    categories: props.revision.categories,
    note: props.revision.note ?? '',
}

const applying = ref(false)

const base = `/admin/posts/${props.post.id}/versions/${props.revision.id}`

/**
 * Approving overwrites a live article, so it asks first. There is no undo
 * beyond opening a new version from the history, and "I meant to press Save"
 * is an easy mistake with two buttons this close together.
 */
function apply(): void {
    if (!window.confirm(`Replace the live "${props.post.title}" with this version?`)) {
        return
    }

    applying.value = true
    router.post(`${base}/apply`, {}, { onFinish: () => (applying.value = false) })
}

function discard(): void {
    if (!window.confirm('Discard this draft version? The live article is untouched.')) {
        return
    }

    router.delete(base)
}
</script>

<template>
    <AdminLayout title="Edit version">
        <div class="mb-8">
            <Link
                :href="`/admin/posts/${post.id}/edit`"
                class="text-sm text-base-content/60 underline underline-offset-4 transition-colors hover:text-base-content"
            >
                &larr; {{ post.title }}
            </Link>

            <div class="mt-3 flex flex-wrap items-center gap-3">
                <h1 class="heading-section text-base-content">New version</h1>
                <span
                    v-if="revision.is_pending"
                    class="rounded-[4px] bg-base-content px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-base-100"
                >
                    Pending approval
                </span>
            </div>

            <p class="mt-2 text-[13px] text-base-content/50">
                The live article keeps serving readers until this is approved. Slug and publish
                date belong to the article and are edited there.
            </p>
        </div>

        <!-- Approve / preview / discard, above the form: this is the screen's
             purpose, and burying it under a 24-row textarea hides it. -->
        <div class="mb-8 flex flex-wrap items-center gap-3 rounded-box border border-base-300 bg-base-200/40 p-4">
            <button
                type="button"
                class="rounded-btn bg-primary px-5 py-2.5 text-sm font-medium text-primary-content transition-opacity hover:opacity-85 disabled:opacity-50"
                :disabled="applying || !diff.hasChanges"
                @click="apply"
            >
                {{ applying ? 'Applying...' : 'Approve &amp; replace live article' }}
            </button>

            <a
                v-if="revision.preview_url"
                :href="revision.preview_url"
                target="_blank"
                rel="noopener"
                class="text-sm text-base-content/60 underline underline-offset-4 transition-colors hover:text-base-content"
            >
                Preview &rarr;
            </a>

            <a
                :href="post.url"
                target="_blank"
                rel="noopener"
                class="text-sm text-base-content/60 underline underline-offset-4 transition-colors hover:text-base-content"
            >
                Live article &rarr;
            </a>

            <button
                type="button"
                class="ml-auto text-sm text-error underline underline-offset-4 transition-opacity hover:opacity-70"
                @click="discard"
            >
                Discard
            </button>
        </div>

        <PostForm
            :initial="initial"
            :authors="authors"
            :categories="categories"
            :audiences="audiences"
            :action="base"
            method="put"
            submit-label="Save version"
            hide-identity-fields
            note-field
        />

        <section class="mt-12">
            <h2 class="heading-card text-[22px] text-base-content">What this changes</h2>
            <p class="mt-2 mb-6 text-[13px] text-base-content/50">
                Against the live article, as it stands right now. Save to refresh.
            </p>

            <VersionChanges :diff="diff" />
        </section>
    </AdminLayout>
</template>
