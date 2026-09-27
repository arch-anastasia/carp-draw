<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

interface PostRow {
    id: string
    title: string
    slug: string
    url: string
    status: 'published' | 'scheduled' | 'draft' | 'killed'
    published_at: string | null
    views_count: number
    reading_minutes: number | null
    authors: string[]
    categories: string[]
}

interface Paginator {
    data: PostRow[]
    current_page: number
    last_page: number
    total: number
    links: { url: string | null; label: string; active: boolean }[]
}

const props = defineProps<{
    posts: Paginator
    filters: { search: string | null; status: string | null }
    stats: { total: number; published: number; drafts: number }
}>()

const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? '')

/** Two-step delete instead of a native confirm(): a browser dialog blocks the
    page, and an accidental Enter on it is unrecoverable. */
const pendingDelete = ref<string | null>(null)

let debounceTimer: ReturnType<typeof setTimeout>

watch([search, status], () => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        router.get(
            '/admin/posts',
            { search: search.value || undefined, status: status.value || undefined },
            { preserveState: true, replace: true },
        )
    }, 300)
})

function destroy(post: PostRow): void {
    if (pendingDelete.value !== post.id) {
        pendingDelete.value = post.id

        return
    }

    router.delete(`/admin/posts/${post.id}`, {
        preserveScroll: true,
        onFinish: () => (pendingDelete.value = null),
    })
}

const statusClass: Record<PostRow['status'], string> = {
    published: 'bg-primary text-primary-content',
    scheduled: 'bg-base-200 text-base-content/80',
    draft: 'border border-base-300 text-base-content/50',
    killed: 'bg-error/10 text-error',
}
</script>

<template>
    <AdminLayout title="Posts">
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow text-base-content/40">Content</p>
                <h1 class="heading-section mt-3 text-base-content">Posts</h1>
            </div>
            <Link
                href="/admin/posts/create"
                class="rounded-btn bg-primary px-5 py-2.5 text-sm font-medium text-primary-content transition-opacity hover:opacity-85"
            >
                New post
            </Link>
        </div>

        <div class="mb-8 grid grid-cols-1 rounded-box border border-base-300 bg-base-100 sm:grid-cols-3">
            <div class="p-6">
                <p class="display-thin text-[26px] tabular-nums text-base-content min-[360px]:text-3xl">{{ stats.total }}</p>
                <p class="eyebrow mt-3 text-base-content/45">Posts</p>
            </div>
            <div class="border-t border-base-300 p-6 sm:border-l sm:border-t-0">
                <p class="display-thin text-[26px] tabular-nums text-base-content min-[360px]:text-3xl">{{ stats.published }}</p>
                <p class="eyebrow mt-3 text-base-content/45">Published</p>
            </div>
            <div class="border-t border-base-300 p-6 sm:border-l sm:border-t-0">
                <p class="display-thin text-[26px] tabular-nums text-base-content min-[360px]:text-3xl">{{ stats.drafts }}</p>
                <p class="eyebrow mt-3 text-base-content/45">Drafts</p>
            </div>
        </div>

        <div class="mb-6 flex flex-wrap gap-3">
            <input
                v-model="search"
                type="search"
                placeholder="Search by title..."
                class="min-w-0 flex-1 rounded-field border border-base-300 bg-base-100 px-3 py-2.5 text-sm text-base-content outline-none transition-colors placeholder:text-base-content/40 focus:border-base-content/40"
            >
            <select
                v-model="status"
                class="rounded-field border border-base-300 bg-base-100 px-3 py-2.5 text-sm text-base-content outline-none transition-colors focus:border-base-content/40"
            >
                <option value="">Active posts</option>
                <option value="published">Published</option>
                <option value="scheduled">Scheduled</option>
                <option value="draft">Draft</option>
                <option value="killed">Killed</option>
            </select>
        </div>

        <div class="overflow-x-auto rounded-box border border-base-300 bg-base-100">
            <table class="w-full text-sm">
                <thead class="border-b border-base-300 bg-base-200">
                    <tr class="text-left">
                        <th class="eyebrow p-4 text-base-content/45">Post</th>
                        <th class="eyebrow p-4 text-base-content/45">Status</th>
                        <th class="eyebrow p-4 text-base-content/45">Views</th>
                        <th class="eyebrow p-4 text-base-content/45"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-base-200">
                    <tr v-if="posts.data.length === 0">
                        <td colspan="4" class="p-8 text-center text-base-content/50">No posts yet.</td>
                    </tr>
                    <tr v-for="post in posts.data" :key="post.id" class="transition-colors hover:bg-base-200/50">
                        <td class="p-4">
                            <div class="font-display font-semibold text-base-content">{{ post.title }}</div>
                            <div class="mt-0.5 text-[13px] text-base-content/55">
                                /{{ post.slug }}
                                <span v-if="post.authors.length"> · {{ post.authors.join(' and ') }}</span>
                                <span v-if="post.categories.length"> · {{ post.categories.join(', ') }}</span>
                            </div>
                        </td>
                        <td class="p-4">
                            <span class="tag" :class="statusClass[post.status]">{{ post.status }}</span>
                            <div v-if="post.published_at" class="mt-1 text-[12px] tabular-nums text-base-content/45">
                                {{ post.published_at }}
                            </div>
                        </td>
                        <td class="p-4 tabular-nums text-base-content/60">{{ post.views_count }}</td>
                        <td class="whitespace-nowrap p-4 text-right">
                            <a v-if="post.status !== 'killed'" :href="post.url" target="_blank" rel="noopener" class="text-base-content/55 underline underline-offset-4 transition-colors hover:text-base-content">View</a>
                            <Link :href="`/admin/posts/${post.id}/edit`" class="ml-4 text-base-content/55 underline underline-offset-4 transition-colors hover:text-base-content">Edit</Link>
                            <button
                                type="button"
                                class="ml-4 underline underline-offset-4 transition-colors"
                                :class="pendingDelete === post.id ? 'text-error' : 'text-base-content/55 hover:text-error'"
                                @click="destroy(post)"
                            >
                                {{ pendingDelete === post.id ? 'Confirm?' : 'Delete' }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="posts.last_page > 1" class="mt-6 flex flex-wrap justify-center gap-1">
            <template v-for="link in posts.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded-field border px-3 py-1.5 text-xs transition-colors"
                    :class="link.active
                        ? 'border-primary bg-primary text-primary-content'
                        : 'border-base-300 text-base-content/70 hover:border-base-content/40 hover:text-base-content'"
                >
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <span v-html="link.label" />
                </Link>
                <!-- eslint-disable-next-line vue/no-v-html -->
                <span v-else class="rounded-field border border-base-300 px-3 py-1.5 text-xs text-base-content/30" v-html="link.label" />
            </template>
        </div>
    </AdminLayout>
</template>
