<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { ArrowLeft, Box } from 'lucide-vue-next'
import { route } from 'ziggy-js'
import AppLayout from '@/Layouts/AppLayout.vue'
</script>

<template>
    <AppLayout title="Photo to Model">
        <div class="dashboard-page">
            <div class="dashboard-intro">
                <span class="eyebrow">CarpDraw workspace</span>
                <h1>Photo to Model</h1>
                <p>Create a model from a product photo.</p>
            </div>
            <section class="project-card" aria-labelledby="photo-model-status">
                <Box :size="32" aria-hidden="true" />
                <h2 id="photo-model-status">Coming soon</h2>
                <p>Photo to Model is not available yet. No credits will be charged.</p>
                <Link class="secondary-button" :href="route('workspace')">
                    <ArrowLeft :size="18" aria-hidden="true" /> Back to workspace
                </Link>
            </section>
        </div>
    </AppLayout>
</template>
