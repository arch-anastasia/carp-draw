<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

interface CategoryRow {
    id: string
    name: string
    slug: string
    url: string
    audience: string | null
    posts_count: number
    published_posts_count: number
}

defineProps<{
    categories: CategoryRow[]
}>()

const pendingDelete = ref<string | null>(null)

function destroy(category: CategoryRow): void {
    if (pendingDelete.value !== category.id) {
        pendingDelete.value = category.id

        return
    }

    router.delete(`/admin/categories/${category.id}`, {
        preserveScroll: true,
        onFinish: () => (pendingDelete.value = null),
    })
}
</script>

<template>
    <AdminLayout title="Categories">
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow text-base-content/40">Content</p>
                <h1 class="heading-section mt-3 text-base-content">Categories</h1>
            </div>
            <Link
                href="/admin/categories/create"
                class="rounded-btn bg-primary px-5 py-2.5 text-sm font-medium text-primary-content transition-opacity hover:opacity-85"
            >
                New category
            </Link>
        </div>

        <div class="overflow-x-auto rounded-box border border-base-300 bg-base-100">
            <table class="w-full text-sm">
                <thead class="border-b border-base-300 bg-base-200">
                    <tr class="text-left">
                        <th class="eyebrow p-4 text-base-content/45">Category</th>
                        <th class="eyebrow p-4 text-base-content/45">Audience</th>
                        <th class="eyebrow p-4 text-base-content/45">Posts</th>
                        <th class="eyebrow p-4 text-base-content/45"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-base-200">
                    <tr v-if="categories.length === 0">
                        <td colspan="4" class="p-8 text-center text-base-content/50">No categories yet.</td>
                    </tr>
                    <tr v-for="category in categories" :key="category.id" class="transition-colors hover:bg-base-200/50">
                        <td class="p-4">
                            <div class="font-display font-semibold text-base-content">{{ category.name }}</div>
                            <div class="mt-0.5 text-[13px] text-base-content/55">/{{ category.slug }}</div>
                        </td>
                        <td class="p-4">
                            <span v-if="category.audience" class="tag bg-base-200 text-base-content/80">{{ category.audience }}</span>
                            <span v-else class="tag border border-base-300 text-base-content/50">everyone</span>
                        </td>
                        <td class="p-4 tabular-nums text-base-content/60">
                            {{ category.published_posts_count }}
                            <span v-if="category.posts_count !== category.published_posts_count" class="text-base-content/40">
                                / {{ category.posts_count }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap p-4 text-right">
                            <a :href="category.url" target="_blank" rel="noopener" class="text-base-content/55 underline underline-offset-4 transition-colors hover:text-base-content">View</a>
                            <Link :href="`/admin/categories/${category.id}/edit`" class="ml-4 text-base-content/55 underline underline-offset-4 transition-colors hover:text-base-content">Edit</Link>
                            <button
                                type="button"
                                class="ml-4 underline underline-offset-4 transition-colors"
                                :class="pendingDelete === category.id ? 'text-error' : 'text-base-content/55 hover:text-error'"
                                @click="destroy(category)"
                            >
                                {{ pendingDelete === category.id ? 'Confirm?' : 'Delete' }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="mt-4 text-xs text-base-content/50">
            Deleting a category unfiles its posts. It never deletes an article.
        </p>
    </AdminLayout>
</template>
