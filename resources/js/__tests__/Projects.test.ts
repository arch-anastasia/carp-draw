import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import Projects from '@/Pages/App/Projects.vue'
import type { ProjectCard } from '@/types/projects'

type FakeForm = Record<string, unknown> & {
    errors: Record<string, string>
    processing: boolean
}

const mocks = vi.hoisted(() => ({
    routerPatch: vi.fn(),
    routerPost: vi.fn(),
    formPost: vi.fn(),
    formPatch: vi.fn(),
    forms: [] as FakeForm[],
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')

    return {
        Head: { name: 'Head', props: ['title'], template: '<div />' },
        Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
        router: { patch: mocks.routerPatch, post: mocks.routerPost },
        usePage: () => ({
            props: {
                auth: {
                    user: {
                        id: 'u1',
                        name: 'Anastasia Ivanova',
                        email: 'anastasia@example.com',
                        is_admin: false,
                        has_password: true,
                        language: 'English',
                    },
                },
                credits: 12,
                flash: { success: null, error: null, status: null, info: null, project: null },
            },
        }),
        useForm: (data: Record<string, unknown>) => {
            const initial = { ...data }
            const form = reactive({
                ...initial,
                errors: {} as Record<string, string>,
                processing: false,
                post: mocks.formPost,
                patch: mocks.formPatch,
                setError(field: string, message: string) {
                    form.errors[field] = message
                },
                clearErrors() {
                    form.errors = {}
                },
                reset() {
                    Object.assign(form, initial)
                },
            })
            mocks.forms.push(form as unknown as FakeForm)
            return form
        },
    }
})

vi.mock('ziggy-js', () => ({
    route: (name: string, params?: unknown) =>
        params === undefined ? `/${name}` : `/${name}/${String(params)}`,
}))

vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: { name: 'AppLayout', props: ['title'], template: '<div><slot /></div>' },
}))

const folders = [
    { id: 'kitchen', name: 'Kitchen' },
    { id: 'bathroom', name: 'Bathroom' },
]

function makeProject(overrides: Partial<ProjectCard> = {}): ProjectCard {
    return {
        id: 'p1',
        title: 'Kitchen specification set',
        type_label: 'Technical drawing',
        updated_human: 'Updated today',
        image_url: null,
        folder_id: 'kitchen',
        workflow: 'drawing',
        ...overrides,
    }
}

function mountProjects(
    props: { folders?: { id: string; name: string }[]; projects?: ProjectCard[] } = {}
) {
    return mount(Projects, {
        props: {
            folders: props.folders ?? folders,
            projects: props.projects ?? [
                makeProject(),
                makeProject({
                    id: 'p2',
                    title: 'Bathroom vanity drawing',
                    type_label: 'Photo to drawing',
                    updated_human: 'Updated yesterday',
                    folder_id: 'bathroom',
                    workflow: 'modelDrawing',
                }),
            ],
        },
    })
}

beforeEach(() => {
    vi.clearAllMocks()
    mocks.forms.splice(0)
})

describe('Projects folders sidebar', () => {
    it('renders the folder filters with per-folder counts', () => {
        const wrapper = mountProjects()

        const buttons = wrapper.findAll('.folder-list button')

        expect(buttons).toHaveLength(3)
        expect(buttons[0].text()).toContain('All projects')
        expect(buttons[0].find('small').text()).toBe('2')
        expect(buttons[1].text()).toContain('Kitchen')
        expect(buttons[1].find('small').text()).toBe('1')
        expect(buttons[2].text()).toContain('Bathroom')
        expect(buttons[2].find('small').text()).toBe('1')
    })

    it('filters the grid to the selected folder', async () => {
        const wrapper = mountProjects()

        await wrapper
            .findAll('.folder-list button')
            .find((button) => button.text().includes('Kitchen'))!
            .trigger('click')

        expect(wrapper.find('#active-folder-title').text()).toBe('Kitchen')
        expect(wrapper.findAll('.project-library-card')).toHaveLength(1)
        expect(wrapper.text()).toContain('Kitchen specification set')
        expect(wrapper.text()).not.toContain('Bathroom vanity drawing')
    })

    it('shows the empty state for a folder without projects', async () => {
        const wrapper = mountProjects({
            projects: [makeProject({ folder_id: 'kitchen' })],
        })

        await wrapper
            .findAll('.folder-list button')
            .find((button) => button.text().includes('Bathroom'))!
            .trigger('click')

        expect(wrapper.find('.empty-folder-state').exists()).toBe(true)
        expect(wrapper.text()).toContain('This folder is empty')
    })
})

describe('Projects move action', () => {
    it('patches the project with the chosen folder_id', async () => {
        const wrapper = mountProjects()

        const select = wrapper.find('select[aria-label="Move Kitchen specification set to folder"]')
        await select.setValue('bathroom')

        expect(mocks.routerPatch).toHaveBeenCalledWith(
            '/projects.update/p1',
            { folder_id: 'bathroom' },
            { preserveScroll: true }
        )
    })
})

describe('Projects create-folder form', () => {
    it('shows the client-side blank message without posting', async () => {
        const wrapper = mountProjects()

        await wrapper.find('[aria-label="Create a new room folder"]').trigger('click')
        await wrapper.find('.new-folder-form').trigger('submit')

        expect(wrapper.find('[role="alert"]').text()).toBe('Enter a room or folder name.')
        expect(mocks.formPost).not.toHaveBeenCalled()
    })

    it('posts a filled name to folders.store', async () => {
        const wrapper = mountProjects()

        await wrapper.find('[aria-label="Create a new room folder"]').trigger('click')
        await wrapper.find('#new-room-folder').setValue('Home office')
        await wrapper.find('.new-folder-form').trigger('submit')

        expect(mocks.formPost).toHaveBeenCalledTimes(1)
        expect(mocks.formPost.mock.calls[0][0]).toBe('/folders.store')
    })

    it('renders the server validation message from form.errors', async () => {
        const wrapper = mountProjects()

        await wrapper.find('[aria-label="Create a new room folder"]').trigger('click')
        mocks.forms[0].errors.name = 'A folder with this name already exists.'
        await nextTick()

        expect(wrapper.find('[role="alert"]').text()).toBe(
            'A folder with this name already exists.'
        )
    })
})
