<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import CategoryForm from '@/Components/Admin/CategoryForm.vue'
import type { CategoryFormValues } from '@/Components/Admin/CategoryForm.vue'

const props = defineProps<{
    category: CategoryFormValues & { id: string; url: string }
    audiences: { value: string; label: string }[]
}>()

const initial: CategoryFormValues = {
    name: props.category.name,
    slug: props.category.slug,
    title: props.category.title ?? '',
    description: props.category.description ?? '',
    audience: props.category.audience,
}
</script>

<template>
    <AdminLayout title="Edit category">
        <div class="mb-8">
            <Link href="/admin/categories" class="text-sm text-base-content/60 underline underline-offset-4 transition-colors hover:text-base-content">
                &larr; Categories
            </Link>
            <h1 class="heading-section mt-3 text-base-content">{{ category.name }}</h1>
        </div>

        <CategoryForm
            :initial="initial"
            :audiences="audiences"
            :action="`/admin/categories/${category.id}`"
            method="put"
            submit-label="Save category"
        />
    </AdminLayout>
</template>
