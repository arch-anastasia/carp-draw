<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import { route } from 'ziggy-js'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FormField from '@/Components/Admin/FormField.vue'
import PostForm from '@/Components/Admin/PostForm.vue'
import type { PostFormValues } from '@/Components/Admin/PostForm.vue'

interface AdminPost extends PostFormValues {
    id: string
    url: string
    status: string
    views_count: number
    killed_at: string | null
    killed_reason: string | null
}

interface Version {
    id: string
    is_pending: boolean
    note: string | null
    created_at: string | null
    applied_at: string | null
    applied_by: string | null
}

const props = defineProps<{
    post: AdminPost
    versions: Version[]
    authors: { id: string; name: string; email: string }[]
    categories: { id: string; name: string }[]
    audiences: { value: string; label: string }[]
}>()

const pending = computed(() => props.versions.find((version) => version.is_pending))
const history = computed(() => props.versions.filter((version) => !version.is_pending))
const isKilled = computed(() => props.post.status === 'killed')
const killForm = useForm({ killed_reason: props.post.killed_reason ?? '' })

function changeKilledState(): void {
    killForm.post(route(isKilled.value ? 'admin.posts.unkill' : 'admin.posts.kill', props.post.id), {
        preserveScroll: true,
        preserveState: false,
    })
}

/**
 * Opening a version is idempotent server-side, so this is "take me to the
 * draft" rather than "make a second one" - see PostRevisionController::store().
 */
function openVersion(): void {
    router.post(`/admin/posts/${props.post.id}/versions`)
}

/** Null columns become empty strings: an input bound to null renders "null". */
const initial: PostFormValues = {
    title: props.post.title,
    slug: props.post.slug,
    authors: props.post.authors,
    excerpt: props.post.excerpt ?? '',
    body: props.post.body,
    cover_image_path: props.post.cover_image_path ?? '',
    meta_title: props.post.meta_title ?? '',
    meta_description: props.post.meta_description ?? '',
    audience: props.post.audience,
    cta_text: props.post.cta_text ?? '',
    cta_url: props.post.cta_url ?? '',
    published_at: props.post.published_at ?? '',
    dated_at: props.post.dated_at ?? '',
    categories: props.post.categories,
}
</script>

<template>
    <AdminLayout title="Edit post">
        <div class="mb-8">
            <Link href="/admin/posts" class="text-sm text-base-content/60 underline underline-offset-4 transition-colors hover:text-base-content">
                &larr; Posts
            </Link>
            <h1 class="heading-section mt-3 text-base-content">{{ post.title }}</h1>
            <p class="mt-2 text-[13px] text-base-content/50">{{ post.status }} · {{ post.views_count }} views</p>
        </div>

        <form class="mb-8 space-y-4 rounded-box border border-base-300 bg-base-100 p-6" @submit.prevent="changeKilledState">
            <h2 class="heading-card text-[18px] text-base-content">{{ isKilled ? 'Killed post' : 'Retire this post' }}</h2>
            <p class="text-sm text-base-content/60">
                {{ isKilled ? 'This post is off the site. Un-kill it first, then publish it separately.' : 'Killing removes the post from listings and returns 410 Gone at its public URL.' }}
            </p>
            <p v-if="isKilled" class="text-sm text-base-content">Reason: {{ post.killed_reason || 'No reason given.' }}</p>
            <FormField v-else label="Kill reason" for-id="killed_reason" optional :error="killForm.errors.killed_reason">
                <input
                    id="killed_reason"
                    v-model="killForm.killed_reason"
                    type="text"
                    maxlength="255"
                    class="w-full rounded-field border border-base-300 bg-base-100 px-3 py-2.5 text-sm text-base-content outline-none focus:border-base-content/40"
                >
            </FormField>
            <button type="submit" :disabled="killForm.processing" class="rounded-btn border border-base-300 px-4 py-2 text-sm text-base-content disabled:opacity-50">
                {{ isKilled ? 'Un-kill post' : 'Kill post' }}
            </button>
        </form>

        <!-- Versions, above the form. Editing below this point changes what
             readers see immediately; a rewrite that should not go live until
             it is finished belongs in a version, and that choice has to be
             visible before someone starts typing rather than after. -->
        <section class="mb-8 rounded-box border border-base-300 bg-base-200/40 p-6">
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2">
                <h2 class="heading-card text-[18px] text-base-content">Versions</h2>
                <p class="text-[13px] text-base-content/50">
                    Edits below go live the moment you save. A version does not.
                </p>
            </div>

            <div v-if="pending" class="mt-5 flex flex-wrap items-center gap-3 rounded-box border border-base-300 bg-base-100 p-4">
                <span class="rounded-[4px] bg-base-content px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-base-100">
                    Pending
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm text-base-content/80">
                        {{ pending.note || 'A new version is being written.' }}
                    </p>
                    <p class="mt-0.5 text-[13px] text-base-content/45">Started {{ pending.created_at }}</p>
                </div>
                <Link
                    :href="`/admin/posts/${post.id}/versions/${pending.id}/edit`"
                    class="rounded-btn bg-primary px-4 py-2 text-sm font-medium text-primary-content transition-opacity hover:opacity-85"
                >
                    Continue
                </Link>
                <a
                    :href="`${post.url}?v=draft`"
                    target="_blank"
                    rel="noopener"
                    class="text-sm text-base-content/60 underline underline-offset-4 transition-colors hover:text-base-content"
                >
                    Preview &rarr;
                </a>
            </div>

            <button
                v-else
                type="button"
                class="mt-5 rounded-btn border border-base-300 bg-base-100 px-4 py-2 text-sm font-medium text-base-content transition-colors hover:border-base-content/40"
                @click="openVersion"
            >
                Start a new version
            </button>

            <div v-if="history.length" class="mt-6 border-t border-base-300 pt-5">
                <p class="eyebrow text-base-content/45">History</p>
                <ul class="mt-3 space-y-2">
                    <li
                        v-for="version in history"
                        :key="version.id"
                        class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-[13px]"
                    >
                        <Link
                            :href="`/admin/posts/${post.id}/versions/${version.id}/changes`"
                            class="text-base-content underline underline-offset-4 transition-opacity hover:opacity-70"
                        >
                            {{ version.applied_at }}
                        </Link>
                        <span v-if="version.applied_by" class="text-base-content/45">by {{ version.applied_by }}</span>
                        <span v-if="version.note" class="min-w-0 truncate text-base-content/60">&mdash; {{ version.note }}</span>
                    </li>
                </ul>
            </div>
        </section>

        <!-- The public URL is linked whatever the status: an admin gets the
             article's own page for a draft too, with the publish controls on
             it. See BlogController::entry(). -->
        <PostForm
            :initial="initial"
            :authors="authors"
            :categories="categories"
            :audiences="audiences"
            :public-url="isKilled ? undefined : post.url"
            :is-killed="isKilled"
            :public-url-label="post.status === 'published' ? 'View on site' : 'Preview'"
            :action="`/admin/posts/${post.id}`"
            method="put"
            submit-label="Save post"
        />
    </AdminLayout>
</template>
