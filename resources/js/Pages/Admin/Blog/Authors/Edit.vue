<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AuthorForm from '@/Components/Admin/AuthorForm.vue'
import type { AuthorFormValues, UserOption } from '@/Components/Admin/AuthorForm.vue'
import { router } from '@inertiajs/vue3'

const props = defineProps<{
    author: AuthorFormValues & { id: string; url: string; posts_count: number; published_posts_count: number }
    users: UserOption[]
}>()

const initial: AuthorFormValues = {
    name: props.author.name,
    username: props.author.username ?? '',
    role: props.author.role ?? '',
    bio: props.author.bio ?? '',
    avatar: null,
    avatar_url: props.author.avatar_url ?? '',
    website_url: props.author.website_url ?? '',
    links: props.author.links ?? [],
    profile_is_published: props.author.profile_is_published,
    user_id: props.author.user_id ?? '',
}

function destroyAuthor(): void {
    if (window.confirm('Delete this unused writer?')) {
        router.delete(`/admin/authors/${props.author.id}`)
    }
}
</script>

<template>
    <AdminLayout title="Edit writer">
        <div class="mb-8">
            <Link href="/admin/authors" class="text-sm text-base-content/60 underline underline-offset-4 transition-colors hover:text-base-content">
                &larr; Writers
            </Link>
            <h1 class="heading-section mt-3 text-base-content">{{ author.name }}</h1>
            <p class="mt-2 text-sm text-base-content/55">
                {{ author.published_posts_count }} published {{ author.published_posts_count === 1 ? 'guide' : 'guides' }}
            </p>
        </div>

        <AuthorForm
            :initial="initial"
            :action="`/admin/authors/${author.id}`"
            method="put"
            submit-label="Save writer"
            :public-url="author.url"
            :users="users"
        />

        <div v-if="author.posts_count === 0" class="mt-8 border-t border-base-300 pt-6">
            <button type="button" class="text-sm text-error underline underline-offset-4" @click="destroyAuthor">Delete writer</button>
        </div>
    </AdminLayout>
</template>
