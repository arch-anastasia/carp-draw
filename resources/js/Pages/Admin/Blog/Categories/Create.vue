<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import CategoryForm from '@/Components/Admin/CategoryForm.vue'
import type { CategoryFormValues } from '@/Components/Admin/CategoryForm.vue'

defineProps<{
    audiences: { value: string; label: string }[]
}>()

const initial: CategoryFormValues = {
    name: '',
    slug: '',
    title: '',
    description: '',
    audience: null,
}
</script>

<template>
    <AdminLayout title="New category">
        <div class="mb-8">
            <Link href="/admin/categories" class="text-sm text-base-content/60 underline underline-offset-4 transition-colors hover:text-base-content">
                &larr; Categories
            </Link>
            <h1 class="heading-section mt-3 text-base-content">New category</h1>
        </div>

        <CategoryForm
            :initial="initial"
            :audiences="audiences"
            action="/admin/categories"
            method="post"
            submit-label="Create category"
        />
    </AdminLayout>
</template>
