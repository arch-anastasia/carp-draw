import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import Plans from '@/Pages/App/Plans.vue'
import type { Plan } from '@/types/billing'

type FakeForm = Record<string, unknown> & {
    errors: Record<string, string>
    processing: boolean
}

const mocks = vi.hoisted(() => ({
    formPost: vi.fn(),
    formPatch: vi.fn(),
    forms: [] as FakeForm[],
    flash: { success: null, error: null, status: null, info: null, project: null } as {
        success: string | null
        error: string | null
        status: string | null
        info: string | null
        project: string | null
    },
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')

    return {
        Head: { name: 'Head', props: ['title'], template: '<div />' },
        Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
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
                flash: mocks.flash,
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

function makePlan(overrides: Partial<Plan> = {}): Plan {
    return {
        key: 'maker',
        name: 'Maker',
        price: 1900,
        currency: 'EUR',
        interval: 'month',
        description: 'For independent makers and small product studies.',
        features: [
            { label: '40 creation credits / month', included: true },
            { label: 'PDF and SVG project briefs', included: true },
            { label: '3D Model to Drawing processing', included: false },
        ],
        popular: false,
        current: false,
        ...overrides,
    }
}

function makeStudioPlan(overrides: Partial<Plan> = {}): Plan {
    return makePlan({
        key: 'studio',
        name: 'Studio',
        price: 8900,
        description: 'For studios running multiple technical workflows.',
        popular: true,
        features: [
            { label: '250 creation credits / month', included: true },
            { label: '3D Model to Drawing processing', included: true },
        ],
        ...overrides,
    })
}

function mountPlans(
    props: {
        plans?: Plan[]
        subscription?: { plan: string; on_grace_period: boolean; ends_at: string | null } | null
    } = {}
) {
    return mount(Plans, {
        props: {
            plans: props.plans ?? [makePlan(), makeStudioPlan()],
            subscription: props.subscription ?? null,
        },
    })
}

function buttonWithText(wrapper: ReturnType<typeof mountPlans>, text: string) {
    return wrapper.findAll('button').find((button) => button.text().includes(text))
}

beforeEach(() => {
    vi.clearAllMocks()
    mocks.forms.splice(0)
    mocks.flash.success = null
    mocks.flash.error = null
})

describe('Plans cards', () => {
    it('renders both plans with their euro prices', () => {
        const wrapper = mountPlans()

        expect(wrapper.findAll('.plan-card')).toHaveLength(2)
        expect(wrapper.text()).toContain('Maker')
        expect(wrapper.text()).toContain('Studio')
        expect(wrapper.text()).toContain('€19')
        expect(wrapper.text()).toContain('€89')
        expect(wrapper.text()).toContain('/ month')
    })

    it('shows the Most popular badge on the popular plan only', () => {
        const wrapper = mountPlans()

        const badges = wrapper.findAll('.popular-label')

        expect(badges).toHaveLength(1)
        const popularCard = wrapper.find('.plan-card.is-popular')
        expect(popularCard.text()).toContain('Studio')
        expect(popularCard.text()).toContain('Most popular')
    })

    it('renders excluded features muted with an X icon', () => {
        const wrapper = mountPlans({ plans: [makePlan()] })

        const muted = wrapper.findAll('li.is-muted')

        expect(muted).toHaveLength(1)
        expect(muted[0].text()).toContain('3D Model to Drawing processing')
    })
})

describe('Plans subscribe action', () => {
    it('posts the chosen plan key to billing.subscribe', async () => {
        const wrapper = mountPlans()

        await buttonWithText(wrapper, 'Choose Maker')!.trigger('click')

        expect(mocks.formPost).toHaveBeenCalledTimes(1)
        expect(mocks.formPost.mock.calls[0][0]).toBe('/billing.subscribe')
        expect(mocks.forms[0].plan).toBe('maker')
        expect(mocks.forms[0].interval).toBe('monthly')
    })

    it('disables the CTA and reads Current plan for the active plan', () => {
        const wrapper = mountPlans({ plans: [makePlan({ current: true }), makeStudioPlan()] })

        const cta = buttonWithText(wrapper, 'Current plan')

        expect(cta).toBeDefined()
        expect(cta!.attributes('disabled')).toBeDefined()
    })

    it('does not post when the current plan CTA is clicked', async () => {
        const wrapper = mountPlans({ plans: [makePlan({ current: true })] })

        await buttonWithText(wrapper, 'Current plan')!.trigger('click')

        expect(mocks.formPost).not.toHaveBeenCalled()
    })
})

describe('Plans notice and grace period', () => {
    it('shows the flash error in the notice slot', () => {
        mocks.flash.error = 'Checkout could not be started.'

        const wrapper = mountPlans()

        expect(wrapper.find('[role="status"]').text()).toBe('Checkout could not be started.')
    })

    it('offers a resume button while the subscription is on grace period', () => {
        const wrapper = mountPlans({
            subscription: { plan: 'maker', on_grace_period: true, ends_at: 'March 3, 2026' },
        })

        expect(wrapper.text()).toContain('Your plan ends March 3, 2026.')
        expect(buttonWithText(wrapper, 'Resume plan')).toBeDefined()
    })
})
