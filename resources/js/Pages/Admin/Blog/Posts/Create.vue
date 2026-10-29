<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PostForm from '@/Components/Admin/PostForm.vue'
import type { PostFormValues } from '@/Components/Admin/PostForm.vue'

defineProps<{
    authors: { id: string; name: string; email: string }[]
    categories: { id: string; name: string }[]
    audiences: { value: string; label: string }[]
}>()

const initial: PostFormValues = {
    title: '',
    slug: '',
    authors: [],
    excerpt: '',
    body: '',
    cover_image_path: '',
    meta_title: '',
    meta_description: '',
    audience: null,
    cta_text: '',
    cta_url: '',
    published_at: '',
    dated_at: '',
    categories: [],
}
</script>

<template>
    <AdminLayout title="New post">
        <div class="mb-8">
            <Link href="/admin/posts" class="text-sm text-base-content/60 underline underline-offset-4 transition-colors hover:text-base-content">
                &larr; Posts
            </Link>
            <h1 class="heading-section mt-3 text-base-content">New post</h1>
        </div>

        <PostForm
            :initial="initial"
            :authors="authors"
            :categories="categories"
            :audiences="audiences"
            action="/admin/posts"
            method="post"
            submit-label="Create post"
        />
    </AdminLayout>
</template>
