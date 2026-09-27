<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
defineProps<{ title?: string }>()
</script>
<template>
    <AppLayout :title="title ?? 'Editorial'">
        <div class="blog-admin">
            <nav class="blog-links" aria-label="Editorial navigation">
                <Link href="/admin/posts">Posts</Link><Link href="/admin/categories">Categories</Link><Link href="/admin/authors">Writers</Link><a href="/blog">View journal</a>
            </nav>
            <slot />
        </div>
    </AppLayout>
</template>
