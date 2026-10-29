<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import {
    ArrowRight,
    CirclePlus,
    Folder,
    FolderOpen,
    FolderPlus,
    Layers3,
} from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { ProjectCard } from '@/types/projects'

const props = defineProps<{
    folders: { id: string; name: string }[]
    projects: ProjectCard[]
}>()

const workflowRoutes: Record<ProjectCard['workflow'], string> = {
    drawing: route('workflow.drawing'),
    modelDrawing: route('workflow.model'),
    specification: route('workflow.specification'),
}

const placeholderImages: Record<ProjectCard['workflow'], string> = {
    drawing: '/examples/example-kitchen-drawing.webp',
    modelDrawing: '/examples/gallery/bathroom-walnut-drawing.webp',
    specification: '/examples/shelving-technical-sheet.webp',
}

const projectImage = (project: ProjectCard) =>
    project.image_url ?? placeholderImages[project.workflow]

const activeFolderId = ref('all')
const isCreatingFolder = ref(false)

const folderForm = useForm({ name: '' })

const visibleProjects = computed(() =>
    activeFolderId.value === 'all'
        ? props.projects
        : props.projects.filter((project) => project.folder_id === activeFolderId.value)
)

const activeFolderName = computed(() =>
    activeFolderId.value === 'all'
        ? 'All projects'
        : (props.folders.find((folder) => folder.id === activeFolderId.value)?.name ?? 'Projects')
)

const getFolderCount = (folderId: string) =>
    folderId === 'all'
        ? props.projects.length
        : props.projects.filter((project) => project.folder_id === folderId).length

const toggleFolderForm = () => {
    isCreatingFolder.value = !isCreatingFolder.value
    folderForm.clearErrors()
}

const createFolder = () => {
    if (!folderForm.name.trim()) {
        folderForm.setError('name', 'Enter a room or folder name.')
        return
    }

    const createdName = folderForm.name.trim().toLowerCase()

    folderForm.post(route('folders.store'), {
        preserveScroll: true,
        onSuccess: () => {
            // The redirect refreshed the folders prop; the new room becomes
            // the active filter, as the original organizer did.
            const created = props.folders.find(
                (folder) => folder.name.toLowerCase() === createdName
            )

            if (created) {
                activeFolderId.value = created.id
            }

            folderForm.reset()
            isCreatingFolder.value = false
        },
    })
}

const moveProject = (project: ProjectCard, event: Event) => {
    const folderId = (event.target as HTMLSelectElement).value
    router.patch(
        route('projects.update', project.id),
        { folder_id: folderId },
        { preserveScroll: true }
    )
}
</script>

<template>
    <AppLayout title="Projects">
        <div class="projects-page app-page-narrow">
            <div class="app-page-heading">
                <div>
                    <span class="eyebrow">Workspace library</span>
                    <h1>Projects</h1>
                    <p>Your saved drawings, model drawing briefs and specifications.</p>
                </div>
                <Link class="primary-button" :href="route('workflow.drawing')">
                    <CirclePlus :size="17" aria-hidden="true" />
                    New project
                </Link>
            </div>
            <div class="projects-organizer">
                <aside class="room-folders" aria-label="Project folders">
                    <div class="room-folders-heading">
                        <div>
                            <span class="eyebrow">Rooms</span>
                            <strong>Project folders</strong>
                        </div>
                        <button
                            type="button"
                            aria-label="Create a new room folder"
                            :aria-expanded="isCreatingFolder"
                            @click="toggleFolderForm"
                        >
                            <FolderPlus :size="18" aria-hidden="true" />
                        </button>
                    </div>

                    <form
                        v-if="isCreatingFolder"
                        class="new-folder-form"
                        @submit.prevent="createFolder"
                    >
                        <label for="new-room-folder">Room or folder name</label>
                        <div>
                            <input
                                id="new-room-folder"
                                v-model="folderForm.name"
                                placeholder="e.g. Home office"
                                autofocus
                                @input="folderForm.clearErrors('name')"
                            />
                            <button type="submit" :disabled="!folderForm.name.trim()">Add</button>
                        </div>
                        <p v-if="folderForm.errors.name" role="alert">
                            {{ folderForm.errors.name }}
                        </p>
                    </form>

                    <nav class="folder-list" aria-label="Filter projects by room">
                        <button
                            :class="activeFolderId === 'all' ? 'is-active' : ''"
                            type="button"
                            :aria-pressed="activeFolderId === 'all'"
                            @click="activeFolderId = 'all'"
                        >
                            <span>
                                <Layers3 :size="17" aria-hidden="true" />
                                All projects
                            </span>
                            <small>{{ getFolderCount('all') }}</small>
                        </button>
                        <button
                            v-for="folder in folders"
                            :key="folder.id"
                            :class="activeFolderId === folder.id ? 'is-active' : ''"
                            type="button"
                            :aria-pressed="activeFolderId === folder.id"
                            @click="activeFolderId = folder.id"
                        >
                            <span>
                                <FolderOpen
                                    v-if="activeFolderId === folder.id"
                                    :size="17"
                                    aria-hidden="true"
                                />
                                <Folder v-else :size="17" aria-hidden="true" />
                                {{ folder.name }}
                            </span>
                            <small>{{ getFolderCount(folder.id) }}</small>
                        </button>
                    </nav>
                </aside>

                <section class="project-folder-content" aria-labelledby="active-folder-title">
                    <div class="project-folder-heading">
                        <div>
                            <span class="eyebrow">Current folder</span>
                            <h2 id="active-folder-title">{{ activeFolderName }}</h2>
                        </div>
                        <span>
                            {{ visibleProjects.length }}
                            {{ visibleProjects.length === 1 ? 'project' : 'projects' }}
                        </span>
                    </div>

                    <div v-if="visibleProjects.length" class="projects-grid">
                        <article
                            v-for="project in visibleProjects"
                            :key="project.id"
                            class="project-library-card"
                        >
                            <div class="project-library-image">
                                <img :src="projectImage(project)" alt="" />
                            </div>
                            <div class="project-library-copy">
                                <div>
                                    <h3>{{ project.title }}</h3>
                                    <p>{{ project.type_label }} · {{ project.updated_human }}</p>
                                </div>
                                <label>
                                    <span>Room</span>
                                    <select
                                        :aria-label="`Move ${project.title} to folder`"
                                        :value="project.folder_id ?? ''"
                                        @change="moveProject(project, $event)"
                                    >
                                        <option
                                            v-for="folder in folders"
                                            :key="folder.id"
                                            :value="folder.id"
                                        >
                                            {{ folder.name }}
                                        </option>
                                    </select>
                                </label>
                            </div>
                            <Link :href="workflowRoutes[project.workflow]">
                                Open project
                                <ArrowRight :size="15" aria-hidden="true" />
                            </Link>
                        </article>
                    </div>
                    <div v-else class="empty-folder-state">
                        <FolderOpen :size="32" aria-hidden="true" />
                        <h3>This folder is empty</h3>
                        <p>Move an existing project here, or start a new drawing for this room.</p>
                        <Link class="primary-button" :href="route('workflow.drawing')">
                            Create project
                            <ArrowRight :size="16" aria-hidden="true" />
                        </Link>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
