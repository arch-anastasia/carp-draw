<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

interface AuthorRow {
    id: string
    name: string
    username: string
    role: string | null
    profile_is_published: boolean
    avatar_url: string | null
    url: string
    posts_count: number
    published_posts_count: number
}

defineProps<{
    authors: AuthorRow[]
}>()

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0]?.toUpperCase() ?? '')
        .join('')
}
</script>

<template>
    <AdminLayout title="Writers">
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow text-base-content/40">Content</p>
                <h1 class="heading-section mt-3 text-base-content">Writers</h1>
            </div>
            <Link
                href="/admin/authors/create"
                class="rounded-btn bg-primary px-5 py-2.5 text-sm font-medium text-primary-content transition-opacity hover:opacity-85"
            >
                New writer
            </Link>
        </div>

        <div class="overflow-x-auto rounded-box border border-base-300 bg-base-100">
            <table class="w-full text-sm">
                <thead class="border-b border-base-300 bg-base-200">
                    <tr class="text-left">
                        <th class="eyebrow p-4 text-base-content/45">Writer</th>
                        <th class="eyebrow p-4 text-base-content/45">Profile</th>
                        <th class="eyebrow p-4 text-base-content/45">Guides</th>
                        <th class="eyebrow p-4 text-base-content/45"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-base-200">
                    <tr v-if="authors.length === 0">
                        <td colspan="4" class="p-8 text-center text-base-content/50">No writers yet.</td>
                    </tr>
                    <tr v-for="author in authors" :key="author.id" class="transition-colors hover:bg-base-200/50">
                        <td class="p-4">
                            <div class="flex items-center gap-3">
                                <img v-if="author.avatar_url" :src="author.avatar_url" alt="" class="h-9 w-9 rounded-full object-cover">
                                <span v-else class="flex h-9 w-9 items-center justify-center rounded-full bg-base-200 text-[11px] font-medium text-base-content/60" aria-hidden="true">
                                    {{ initials(author.name) }}
                                </span>
                                <div>
                                    <div class="font-display font-semibold text-base-content">{{ author.name }}</div>
                                    <div v-if="author.role" class="mt-0.5 text-[13px] text-base-content/55">{{ author.role }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="p-4">
                            <span v-if="author.profile_is_published" class="tag bg-base-200 text-base-content/80">/{{ author.username }}</span>
                            <span v-else class="tag border border-base-300 text-base-content/50">unpublished</span>
                        </td>
                        <td class="p-4 tabular-nums text-base-content/60">
                            {{ author.published_posts_count }}
                            <span v-if="author.posts_count !== author.published_posts_count" class="text-base-content/40">
                                / {{ author.posts_count }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap p-4 text-right">
                            <a
                                v-if="author.profile_is_published"
                                :href="author.url"
                                target="_blank"
                                rel="noopener"
                                class="text-base-content/55 underline underline-offset-4 transition-colors hover:text-base-content"
                            >
                                View
                            </a>
                            <Link :href="`/admin/authors/${author.id}/edit`" class="ml-4 text-base-content/55 underline underline-offset-4 transition-colors hover:text-base-content">
                                Edit
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="mt-4 text-xs text-base-content/50">
            Unpublishing takes a profile page down and leaves its articles signed. Writers with no guides can be deleted from their edit screen.
        </p>
    </AdminLayout>
</template>
