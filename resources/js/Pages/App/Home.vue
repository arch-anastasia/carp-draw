<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { ArrowRight, Box, FileSpreadsheet, Sparkles } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { ProjectCard } from '@/types/projects'

const props = defineProps<{
    recentProjects: ProjectCard[]
}>()

const creationModes = [
    {
        href: route('workflow.drawing'),
        title: 'Photo to Drawing',
        copy: 'Turn a product photo or sketch into a structured technical drawing.',
        icon: Sparkles,
        image: '/examples/step-photo.webp',
        label: 'PHOTO / 01',
    },
    {
        href: route('workflow.model'),
        title: '3D Model to Drawing',
        copy: 'Upload a 3D model and prepare clear orthographic drawing views.',
        icon: Box,
        image: '/examples/step-drawing.webp',
        label: 'MODEL / 02',
    },
    {
        href: route('workflow.specification'),
        title: 'Create Specification',
        copy: 'Organize dimensions, materials, notes and outputs in one specification.',
        icon: FileSpreadsheet,
        image: '/examples/create-specification-text-workflow-v2.webp',
        label: 'SPEC / 03',
    },
]

const placeholderImages: Record<ProjectCard['workflow'], string> = {
    drawing: '/examples/example-kitchen-drawing.webp',
    modelDrawing: '/examples/gallery/bathroom-walnut-drawing.webp',
    specification: '/examples/shelving-technical-sheet.webp',
}

const recentProjects = computed(() => props.recentProjects.slice(0, 4))

const projectImage = (project: ProjectCard) =>
    project.image_url ?? placeholderImages[project.workflow]
</script>

<template>
    <AppLayout title="Workspace">
        <div class="dashboard-page">
            <div class="dashboard-intro">
                <span class="eyebrow">CarpDraw workspace</span>
                <h1>What would you like to create?</h1>
                <p>
                    Choose a focused workflow. Each route guides the technical decisions one step
                    at a time.
                </p>
            </div>
            <div class="creation-grid">
                <Link
                    v-for="mode in creationModes"
                    :key="mode.href"
                    class="creation-card"
                    :href="mode.href"
                >
                    <div class="creation-card-top">
                        <component :is="mode.icon" :size="19" aria-hidden="true" />
                        <span>{{ mode.title }}</span>
                        <ArrowRight :size="18" aria-hidden="true" />
                    </div>
                    <div class="creation-image">
                        <img :src="mode.image" alt="" />
                        <span>{{ mode.label }}</span>
                    </div>
                    <p>{{ mode.copy }}</p>
                </Link>
            </div>
            <Link class="try-banner" :href="route('workflow.drawing')">
                <div>
                    <span>New workflow</span>
                    <p>Photo or sketch to dimensioned technical drawing</p>
                </div>
                <strong>Try Drawing <span>V2</span></strong>
                <ArrowRight aria-hidden="true" />
            </Link>
            <section class="recent-section">
                <div class="app-section-heading">
                    <div>
                        <span class="eyebrow">Saved work</span>
                        <h2>Recent projects</h2>
                    </div>
                    <Link :href="route('projects.index')">
                        View all <ArrowRight :size="15" aria-hidden="true" />
                    </Link>
                </div>
                <template v-if="recentProjects.length">
                    <Link
                        v-for="project in recentProjects"
                        :key="project.id"
                        class="project-card project-card-image"
                        :href="route('projects.index')"
                    >
                        <img :src="projectImage(project)" :alt="project.title" />
                        <strong>{{ project.title }}</strong>
                        <span>{{ project.updated_human }}</span>
                    </Link>
                </template>
                <Link
                    v-else
                    class="project-card project-card-image"
                    :href="route('workflow.drawing')"
                >
                    <img src="/examples/example-kitchen-drawing.webp" alt="" />
                    <strong>No saved projects yet</strong>
                    <span>Run a workflow to create your first drawing.</span>
                </Link>
            </section>
        </div>
    </AppLayout>
</template>
