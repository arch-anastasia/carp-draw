<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AuthorForm from '@/Components/Admin/AuthorForm.vue'
import type { AuthorFormValues, UserOption } from '@/Components/Admin/AuthorForm.vue'

defineProps<{ users: UserOption[] }>()

const initial: AuthorFormValues = {
    name: '',
    username: '',
    role: '',
    bio: '',
    avatar: null,
    avatar_url: '',
    website_url: '',
    links: [],
    profile_is_published: true,
    user_id: '',
}
</script>

<template>
    <AdminLayout title="New writer">
        <div class="mb-8">
            <Link href="/admin/authors" class="text-sm text-base-content/60 underline underline-offset-4 transition-colors hover:text-base-content">
                &larr; Writers
            </Link>
            <h1 class="heading-section mt-3 text-base-content">New writer</h1>
        </div>

        <AuthorForm :initial="initial" action="/admin/authors" method="post" submit-label="Create writer" :users="users" />
    </AdminLayout>
</template>
