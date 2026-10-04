import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import WorkflowShow from '@/Pages/Workflow/Show.vue'

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', props: ['title'], template: '<div />' },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({
        props: {
            auth: { user: null },
            branding: { name: 'CarpDraw' },
            flash: {},
        },
    }),
    useForm: (fields: Record<string, unknown>) => ({
        ...fields,
        post: vi.fn(),
        processing: false,
        errors: {},
    }),
    router: { visit: vi.fn(), post: vi.fn() },
}))

vi.mock('ziggy-js', () => ({
    route: (name: string) => `/${name}`,
}))

vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: {
        name: 'AppLayout',
        props: ['title'],
        template: '<div><slot /></div>',
    },
}))

vi.mock('@/Components/Workflow/TechnicalVisual.vue', () => ({
    default: {
        name: 'TechnicalVisual',
        props: ['label'],
        template: '<div class="technical-visual">{{ label }}</div>',
    },
}))

const fetchMock = vi.fn()
vi.stubGlobal('fetch', fetchMock)

type Mode = 'drawing' | 'modelDrawing' | 'specification'

function mountShow(mode: Mode = 'drawing') {
    return mount(WorkflowShow, {
        props: { mode, credits: 12, cost: 4, canModelDrawing: true },
    })
}

type ShowWrapper = ReturnType<typeof mountShow>

function jsonResponse(status: number, payload: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => payload,
    } as Response
}

async function settle() {
    for (let index = 0; index < 6; index += 1) {
        await Promise.resolve()
    }
    await nextTick()
}

async function attachFile(
    wrapper: ShowWrapper,
    name = 'chair.png',
    type = 'image/png',
    size?: number
) {
    const file = new File(['reference'], name, { type })
    if (size !== undefined) {
        Object.defineProperty(file, 'size', { value: size })
    }
    const input = wrapper.find('input[type="file"]')
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
    await input.trigger('change')
    return file
}

function continueButton(wrapper: ShowWrapper) {
    const button = wrapper.findAll('button').find((candidate) => {
        return candidate.text().includes('Continue')
    })

    expect(button).toBeDefined()
    return button!
}

async function clickContinue(wrapper: ShowWrapper) {
    await continueButton(wrapper).trigger('click')
}

async function goToDrawingReview(wrapper: ShowWrapper) {
    await attachFile(wrapper)
    await clickContinue(wrapper)
    await wrapper.find('#furniture-width').setValue('2000')
    await wrapper.find('#furniture-height').setValue('850')
    await clickContinue(wrapper)
}

beforeEach(() => {
    fetchMock.mockReset()
    URL.createObjectURL = vi.fn(() => 'blob:preview') as unknown as typeof URL.createObjectURL
    URL.revokeObjectURL = vi.fn() as unknown as typeof URL.revokeObjectURL
})

afterEach(() => {
    vi.useRealTimers()
})

describe('Workflow upload stage', () => {
    it('renders the drawing mode copy', () => {
        const wrapper = mountShow('drawing')

        expect(wrapper.text()).toContain('New drawing')
        expect(wrapper.text()).toContain('Step 01 · Source')
        expect(wrapper.text()).toContain('Upload a product photo')
        expect(wrapper.text()).toContain('For best results, upload a clear photo showing the whole piece of furniture, with no parts cut off.')
        expect(wrapper.text()).toContain('Drop, paste or click to upload a product photo')
        expect(wrapper.text()).toContain('JPG, PNG, HEIC or WebP · up to 15 MB')
        expect(wrapper.text()).toContain('Measure')
    })

    it('renders the model drawing mode copy', () => {
        const wrapper = mountShow('modelDrawing')

        expect(wrapper.text()).toContain('New model drawing')
        expect(wrapper.text()).toContain('Upload a 3D model')
        expect(wrapper.text()).toContain('Use a clean model with correctly grouped geometry.')
        expect(wrapper.text()).toContain('Drop or click to upload a 3D model')
        expect(wrapper.text()).toContain('SKP, 3DS, OBJ or DWG · up to 50 MB')
        expect(wrapper.text()).toContain('Views')
    })

    it('renders the specification mode copy', () => {
        const wrapper = mountShow('specification')

        expect(wrapper.text()).toContain('New specification')
        expect(wrapper.text()).toContain('Upload your project reference')
        expect(wrapper.text()).toContain('Add a drawing, model or product image as the source.')
        expect(wrapper.text()).toContain('Drop, paste or click to upload a project reference')
        expect(wrapper.text()).toContain('Image, PDF, SKP, 3DS, OBJ, DWG or DXF · up to 50 MB')
        expect(wrapper.text()).toContain('Details')
    })

    it('passes the per-mode metadata title to the layout', () => {
        expect(mountShow('drawing').findComponent({ name: 'AppLayout' }).props('title')).toBe(
            'Photo to Drawing'
        )
        expect(mountShow('modelDrawing').findComponent({ name: 'AppLayout' }).props('title')).toBe(
            '3D Model to Drawing'
        )
        expect(mountShow('specification').findComponent({ name: 'AppLayout' }).props('title')).toBe(
            'Create Specification'
        )
    })

    it('sets the per-mode accept string on the file input', () => {
        expect(mountShow('drawing').find('input[type="file"]').attributes('accept')).toBe(
            '.jpg,.jpeg,.png,.webp,.heic,.heif,image/*'
        )
        expect(mountShow('modelDrawing').find('input[type="file"]').attributes('accept')).toBe(
            '.skp,.3ds,.obj,.dwg'
        )
        expect(mountShow('specification').find('input[type="file"]').attributes('accept')).toBe(
            '.jpg,.jpeg,.png,.webp,.pdf,.skp,.3ds,.obj,.dwg,.dxf'
        )
    })

    it('disables Continue until a file has been chosen', async () => {
        const wrapper = mountShow('drawing')

        expect(continueButton(wrapper).attributes('disabled')).toBeDefined()

        await attachFile(wrapper)

        expect(continueButton(wrapper).attributes('disabled')).toBeUndefined()
        expect(wrapper.text()).toContain('chair.png')
    })

    it('rejects an unsupported extension with the formats message', async () => {
        const wrapper = mountShow('drawing')

        await attachFile(wrapper, 'notes.txt', 'text/plain')

        expect(wrapper.find('[role="alert"]').text()).toBe(
            'Supported formats: JPG, JPEG, PNG, WEBP, HEIC, HEIF.'
        )
        expect(continueButton(wrapper).attributes('disabled')).toBeDefined()
    })

    it('rejects a file above the mode size cap', async () => {
        const wrapper = mountShow('drawing')

        await attachFile(wrapper, 'huge.png', 'image/png', 16 * 1024 * 1024)

        expect(wrapper.find('[role="alert"]').text()).toBe('The file is larger than 15 MB.')
    })
})

describe('Workflow measure stage', () => {
    it('renders the measurement tool for a drawing image preview', async () => {
        const wrapper = mountShow('drawing')

        await attachFile(wrapper)
        await clickContinue(wrapper)

        expect(wrapper.text()).toContain('Step 02 · Measure')
        expect(wrapper.text()).toContain('Select furniture & set real dimensions')
        expect(wrapper.find('.measurement-canvas').exists()).toBe(true)
        expect(wrapper.text()).toContain('Outline one furniture item')
    })

    it('replaces the preview note with the secure processing note', async () => {
        const wrapper = mountShow('drawing')

        await attachFile(wrapper)
        await clickContinue(wrapper)

        expect(wrapper.text()).toContain('Secure processing')
        expect(wrapper.text()).toContain(
            'Files upload over an encrypted connection and stay private to your workspace.'
        )
        expect(wrapper.text()).not.toContain('Preview mode')
        expect(wrapper.text()).not.toContain('Files remain in this browser.')
    })

    it('blocks the drawing mode without valid width and height', async () => {
        const wrapper = mountShow('drawing')

        await attachFile(wrapper)
        await clickContinue(wrapper)
        await clickContinue(wrapper)

        expect(wrapper.find('[role="alert"]').text()).toBe(
            'Add valid width and height measurements.'
        )
        expect(wrapper.text()).toContain('Select furniture & set real dimensions')
    })

    it('blocks the specification mode without a project name', async () => {
        const wrapper = mountShow('specification')

        await attachFile(wrapper, 'reference.pdf', 'application/pdf')
        await clickContinue(wrapper)

        expect(wrapper.text()).toContain('Add the specification details')

        await clickContinue(wrapper)

        expect(wrapper.find('[role="alert"]').text()).toBe('Add a project or product name.')
    })
})

describe('Workflow review and submission', () => {
    it('shows the prepared brief and posts the drawing FormData on Continue', async () => {
        const wrapper = mountShow('drawing')

        await goToDrawingReview(wrapper)

        expect(wrapper.text()).toContain('Review the prepared brief')
        expect(wrapper.find('.review-panel').text()).toContain('chair.png')
        expect(wrapper.find('.review-panel').text()).toContain('2000 × 850 mm')

        fetchMock.mockResolvedValueOnce(jsonResponse(200, { project: 'p-123' }))
        await clickContinue(wrapper)
        await settle()

        expect(fetchMock).toHaveBeenCalledTimes(1)
        const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit]
        expect(url).toBe('/workflow.store')
        expect(init.method).toBe('POST')
        expect((init.headers as Record<string, string>).Accept).toBe('application/json')

        const body = init.body as FormData
        expect(body.get('mode')).toBe('drawing')
        expect(body.get('unit')).toBe('mm')
        expect(body.get('width')).toBe('2000')
        expect(body.get('height')).toBe('850')
        expect(body.get('depth')).toBeNull()
        // The untouched default must be a value the server's closed list
        // accepts — the first option of the mode's select.
        expect(body.get('style')).toBe('furniture four views')
        expect(body.get('file')).toBeInstanceOf(File)

        expect(wrapper.text()).toContain('Preparing your result')
        expect(wrapper.text()).toContain('Preparing your drawing…')
        expect(wrapper.find('.workflow-processing').exists()).toBe(true)

        wrapper.unmount()
    })

    it('posts the model drawing fields and surfaces a plan_required message', async () => {
        const wrapper = mountShow('modelDrawing')

        await attachFile(wrapper, 'cabinet.skp', '')
        await clickContinue(wrapper)
        await clickContinue(wrapper)

        expect(wrapper.text()).toContain('Review the prepared brief')

        fetchMock.mockResolvedValueOnce(
            jsonResponse(403, {
                error: 'plan_required',
                message: '3D model processing is available on the Studio plan.',
            })
        )
        await clickContinue(wrapper)
        await settle()

        const [, init] = fetchMock.mock.calls[0] as [string, RequestInit]
        const body = init.body as FormData
        expect(body.get('mode')).toBe('modelDrawing')
        expect(body.get('view_set')).toBe('front side and plan views')
        expect(body.get('model_units')).toBe('mm')
        expect(body.get('width')).toBeNull()

        expect(wrapper.find('[role="alert"]').text()).toBe(
            '3D model processing is available on the Studio plan.'
        )
        expect(wrapper.findAll('a').some((link) => link.text().includes('Get more credits'))).toBe(
            false
        )
    })

    it('shows the credits link when the server rejects with insufficient_credits', async () => {
        const wrapper = mountShow('drawing')

        await goToDrawingReview(wrapper)

        fetchMock.mockResolvedValueOnce(
            jsonResponse(402, {
                error: 'insufficient_credits',
                message: 'You need more credits to run this workflow.',
            })
        )
        await clickContinue(wrapper)
        await settle()

        expect(wrapper.find('[role="alert"]').text()).toBe(
            'You need more credits to run this workflow.'
        )
        const creditsLink = wrapper.findAll('a').find((link) => {
            return link.text().includes('Get more credits')
        })
        expect(creditsLink).toBeDefined()
        expect(creditsLink!.attributes('href')).toBe('/billing.index')
        expect(creditsLink!.classes()).toContain('primary-button')
        expect(wrapper.text()).toContain('Review the prepared brief')
    })

    it('shows the first validation message from a 422 response', async () => {
        const wrapper = mountShow('drawing')

        await goToDrawingReview(wrapper)

        fetchMock.mockResolvedValueOnce(
            jsonResponse(422, { errors: { width: ['The width field is required.'] } })
        )
        await clickContinue(wrapper)
        await settle()

        expect(wrapper.find('[role="alert"]').text()).toBe('The width field is required.')
    })

    it('polls the status and swaps in the result image on success', async () => {
        vi.useFakeTimers()
        const wrapper = mountShow('drawing')

        await goToDrawingReview(wrapper)

        fetchMock.mockResolvedValueOnce(jsonResponse(200, { project: 'p-123' }))
        fetchMock.mockResolvedValueOnce(
            jsonResponse(200, {
                id: 'p-123',
                status: 'succeeded',
                output_url: '/app/projects/p-123/image',
                error: null,
                credits_available: 8,
            })
        )

        await clickContinue(wrapper)
        await settle()

        expect(wrapper.text()).toContain('Preparing your drawing…')

        await vi.advanceTimersByTimeAsync(2000)
        await settle()

        expect(fetchMock).toHaveBeenCalledTimes(2)
        expect((fetchMock.mock.calls[1] as [string, RequestInit])[0]).toBe('/projects.status')

        const image = wrapper.find('img.result-image')
        expect(image.exists()).toBe(true)
        expect(image.attributes('src')).toBe('/app/projects/p-123/image')
        expect(image.attributes('alt')).toBe('Generated technical drawing')
        expect(wrapper.text()).toContain('Drawing concept ready')
        expect(wrapper.text()).toContain(
            'Review the generated concept against your source photo. Only your supplied overall dimensions are verified; this image is not an editable 3D model or a manufacturing drawing.'
        )

        const saveLink = wrapper.findAll('a').find((link) => {
            return link.text().includes('Save to projects')
        })
        expect(saveLink).toBeDefined()
        expect(saveLink!.attributes('href')).toBe('/projects.index')

        const startAnother = wrapper.findAll('button').find((button) => {
            return button.text().includes('Start another')
        })
        await startAnother!.trigger('click')

        expect(wrapper.text()).toContain('Upload a product photo')

        wrapper.unmount()
    })

    it('shows the failure state with the server error message', async () => {
        vi.useFakeTimers()
        const wrapper = mountShow('drawing')

        await goToDrawingReview(wrapper)

        fetchMock.mockResolvedValueOnce(jsonResponse(200, { project: 'p-123' }))
        fetchMock.mockResolvedValueOnce(
            jsonResponse(200, {
                id: 'p-123',
                status: 'failed',
                output_url: null,
                error: 'The source photo could not be processed.',
                credits_available: 12,
            })
        )

        await clickContinue(wrapper)
        await settle()
        await vi.advanceTimersByTimeAsync(2000)
        await settle()

        expect(wrapper.text()).toContain('Processing failed')
        expect(wrapper.find('[role="alert"]').text()).toBe(
            'The source photo could not be processed.'
        )
        expect(wrapper.find('.result-check').exists()).toBe(false)
        expect(
            wrapper.findAll('button').some((button) => button.text().includes('Start another'))
        ).toBe(true)

        wrapper.unmount()
    })
})
