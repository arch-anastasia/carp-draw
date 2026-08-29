/**
 * CarpDraw marketing bundle.
 *
 * The marketing pages are Blade-only and served under a `script-src 'self'`
 * Content-Security-Policy, so no inline <script> tags or event handlers exist
 * in those templates. Every behaviour is bound here through data-* hooks,
 * no-ops when a page has none of a feature's hooks, and init() is idempotent:
 * elements are marked once bound, so a second pass only picks up new markup.
 */

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

/**
 * Bind an element once. Returns false when it has already been bound, so a
 * repeated init() pass skips it.
 */
function bindOnce(element: HTMLElement, feature: string): boolean {
    const key = `carpdrawBound${feature}`

    if (element.dataset[key] === '1') {
        return false
    }

    element.dataset[key] = '1'

    return true
}

function queryAll<T extends HTMLElement>(selector: string, root: ParentNode = document): T[] {
    return Array.from(root.querySelectorAll<T>(selector))
}

/** Laravel's XSRF-TOKEN cookie, URL-decoded for the X-XSRF-TOKEN header. */
function getXsrfToken(): string {
    const prefix = 'XSRF-TOKEN='
    const row = document.cookie.split('; ').find((cookie) => cookie.startsWith(prefix))

    return row ? decodeURIComponent(row.slice(prefix.length)) : ''
}

/**
 * Show/hide helpers. The hidden attribute alone is not enough here: several
 * of these elements (the auth backdrop, the product menu, the contact success
 * card) carry a CSS display value that overrides the user-agent [hidden]
 * rule, so an inline display is toggled alongside the attribute — matching
 * the `style="display: none"` the Blade templates render them with.
 */
function show(element: HTMLElement): void {
    element.hidden = false
    element.style.removeProperty('display')
}

function hide(element: HTMLElement): void {
    element.hidden = true
    element.style.display = 'none'
}

/** The first server-side validation error for one field, or null. */
function serverFieldError(payload: unknown, field: string): string | null {
    if (typeof payload !== 'object' || payload === null) {
        return null
    }

    const errors = (payload as { errors?: unknown }).errors

    if (typeof errors !== 'object' || errors === null) {
        return null
    }

    const fieldErrors = (errors as Record<string, unknown>)[field]

    if (Array.isArray(fieldErrors) && typeof fieldErrors[0] === 'string') {
        return fieldErrors[0]
    }

    return null
}

/** The response's top-level message, or null. */
function serverMessage(payload: unknown): string | null {
    if (typeof payload !== 'object' || payload === null) {
        return null
    }

    const message = (payload as { message?: unknown }).message

    return typeof message === 'string' && message !== '' ? message : null
}

/**
 * Header "Product" dropdown. As in the original, it closes on link click
 * only — no outside-click or Escape handling.
 */
function initProductDropdown(): void {
    const trigger = document.querySelector<HTMLButtonElement>('[data-nav-dropdown]')

    if (!trigger || !bindOnce(trigger, 'NavDropdown')) {
        return
    }

    const menu = trigger
        .closest('.nav-dropdown')
        ?.querySelector<HTMLElement>('[data-nav-dropdown-menu]')

    if (!menu) {
        return
    }

    const setOpen = (open: boolean): void => {
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false')

        if (open) {
            show(menu)
        } else {
            hide(menu)
        }
    }

    trigger.addEventListener('click', () => setOpen(menu.hidden))

    queryAll<HTMLAnchorElement>('a', menu).forEach((link) => {
        link.addEventListener('click', () => setOpen(false))
    })
}

/** Mobile hamburger navigation. Closes when any of its items is activated. */
function initMobileNav(): void {
    const toggle = document.querySelector<HTMLButtonElement>('[data-mobile-nav-toggle]')
    const nav = document.querySelector<HTMLElement>('[data-mobile-nav]')

    if (!toggle || !nav || !bindOnce(toggle, 'MobileNav')) {
        return
    }

    const openIcon = toggle.querySelector<SVGSVGElement>('[data-nav-icon="open"]')
    const closeIcon = toggle.querySelector<SVGSVGElement>('[data-nav-icon="close"]')

    const setOpen = (open: boolean): void => {
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false')
        toggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation')

        if (openIcon) {
            openIcon.style.display = open ? 'none' : ''
        }

        if (closeIcon) {
            closeIcon.style.display = open ? '' : 'none'
        }

        if (open) {
            show(nav)
        } else {
            hide(nav)
        }
    }

    toggle.addEventListener('click', () => setOpen(nav.hidden))

    queryAll<HTMLElement>('a, [data-auth-open]', nav).forEach((item) => {
        item.addEventListener('click', () => setOpen(false))
    })
}

/**
 * Sign-in modal: opened by any [data-auth-open] trigger, closed via backdrop
 * mousedown, the close button or Escape. body.modal-open suppresses page
 * scrolling while it is up.
 */
function initAuthModal(): void {
    const modal = document.querySelector<HTMLElement>('[data-auth-modal]')

    if (!modal) {
        return
    }

    const emailInput = modal.querySelector<HTMLInputElement>('#auth-email')

    const openModal = (): void => {
        show(modal)
        document.body.classList.add('modal-open')
        emailInput?.focus()
    }

    const closeModal = (): void => {
        hide(modal)
        document.body.classList.remove('modal-open')
    }

    if (bindOnce(modal, 'AuthModal')) {
        modal.addEventListener('mousedown', (event) => {
            if (event.target === modal) {
                closeModal()
            }
        })

        modal
            .querySelector<HTMLButtonElement>('[data-auth-close]')
            ?.addEventListener('click', closeModal)

        window.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.hidden) {
                closeModal()
            }
        })
    }

    queryAll<HTMLElement>('[data-auth-open]').forEach((opener) => {
        if (bindOnce(opener, 'AuthOpen')) {
            opener.addEventListener('click', openModal)
        }
    })
}

/** FAQ accordion: single-open, first item open by default (server-rendered). */
function initFaq(): void {
    const list = document.querySelector<HTMLElement>('[data-faq-list]')

    if (!list || !bindOnce(list, 'Faq')) {
        return
    }

    const toggles = queryAll<HTMLButtonElement>('[data-faq-toggle]', list)

    const setOpen = (toggle: HTMLButtonElement, open: boolean): void => {
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false')
        toggle.closest('.faq-item')?.classList.toggle('is-open', open)

        const answerId = toggle.getAttribute('aria-controls')
        const answer = answerId ? document.getElementById(answerId) : null

        if (answer) {
            answer.hidden = !open
        }
    }

    toggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const willOpen = toggle.getAttribute('aria-expanded') !== 'true'

            toggles.forEach((other) => setOpen(other, other === toggle && willOpen))
        })
    })
}

interface SignInOutcome {
    ok: boolean
    message: string
}

async function requestSignInLink(form: HTMLFormElement, email: string): Promise<SignInOutcome> {
    try {
        const response = await fetch(form.getAttribute('action') ?? '/signin/email', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: JSON.stringify({ email }),
        })

        if (response.ok) {
            return { ok: true, message: '' }
        }

        const payload: unknown = await response.json().catch(() => null)

        return {
            ok: false,
            message:
                serverFieldError(payload, 'email') ??
                serverMessage(payload) ??
                'Enter a valid email address.',
        }
    } catch {
        return { ok: false, message: 'Enter a valid email address.' }
    }
}

/** Passwordless email form inside the sign-in modal. */
function initAuthEmailForm(): void {
    const form = document.querySelector<HTMLFormElement>('[data-auth-email-form]')

    if (!form || !bindOnce(form, 'AuthEmailForm')) {
        return
    }

    const panel = form.closest<HTMLElement>('[data-auth-modal]') ?? document.body
    const input = form.querySelector<HTMLInputElement>('input[type="email"]')
    const submit = form.querySelector<HTMLButtonElement>('button[type="submit"]')
    const errorSlot = panel.querySelector<HTMLElement>('[data-auth-error]')
    const messageSlot = panel.querySelector<HTMLElement>('[data-auth-message]')

    if (!input || !submit) {
        return
    }

    const idleLabel = submit.textContent ?? 'Continue with email'
    let submitting = false

    const clearError = (): void => {
        if (errorSlot) {
            hide(errorSlot)
        }

        input.setAttribute('aria-invalid', 'false')
        input.removeAttribute('aria-describedby')
    }

    const showError = (text: string): void => {
        if (messageSlot) {
            hide(messageSlot)
        }

        if (errorSlot) {
            errorSlot.textContent = text
            show(errorSlot)
        }

        input.setAttribute('aria-invalid', 'true')
        input.setAttribute('aria-describedby', 'auth-error')
    }

    const showMessage = (text: string): void => {
        clearError()

        if (messageSlot) {
            messageSlot.textContent = text
            show(messageSlot)
        }
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault()

        if (submitting) {
            return
        }

        const cleanEmail = input.value.trim()

        if (!EMAIL_PATTERN.test(cleanEmail)) {
            showError('Enter a valid email address.')

            return
        }

        submitting = true
        submit.disabled = true
        submit.textContent = 'Preparing...'
        clearError()

        void requestSignInLink(form, cleanEmail)
            .then((result) => {
                if (result.ok) {
                    showMessage(`A secure sign-in link has been sent to ${cleanEmail}.`)
                } else {
                    showError(result.message)
                }
            })
            .finally(() => {
                submitting = false
                submit.disabled = false
                submit.textContent = idleLabel
            })
    })
}

type ContactField = 'name' | 'email' | 'subject' | 'message'

const CONTACT_FIELDS: ContactField[] = ['name', 'email', 'subject', 'message']

/**
 * Fields whose error paragraph is wired to the control via aria-describedby.
 * The subject select intentionally is not — matching the original markup.
 */
const CONTACT_DESCRIBED: Partial<Record<ContactField, string>> = {
    name: 'contact-name-error',
    email: 'contact-email-error',
    message: 'contact-message-error',
}

interface ContactOutcome {
    ok: boolean
    errors: Partial<Record<ContactField, string>>
}

async function sendContactMessage(
    form: HTMLFormElement,
    values: Record<ContactField, string>
): Promise<ContactOutcome> {
    try {
        const response = await fetch(form.getAttribute('action') ?? '/contact', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: JSON.stringify(values),
        })

        if (response.ok) {
            return { ok: true, errors: {} }
        }

        const payload: unknown = await response.json().catch(() => null)
        const errors: Partial<Record<ContactField, string>> = {}

        CONTACT_FIELDS.forEach((field) => {
            const fieldError = serverFieldError(payload, field)

            if (fieldError) {
                errors[field] = fieldError
            }
        })

        if (!Object.keys(errors).length) {
            errors.message = serverMessage(payload) ?? 'Something went wrong. Try again.'
        }

        return { ok: false, errors }
    } catch {
        return { ok: false, errors: { message: 'Something went wrong. Try again.' } }
    }
}

/** Contact form: client validation mirroring the original, then a real POST. */
function initContactForm(): void {
    const form = document.querySelector<HTMLFormElement>('[data-contact-form]')

    if (!form || !bindOnce(form, 'ContactForm')) {
        return
    }

    const success = document.querySelector<HTMLElement>('[data-contact-success]')
    const submit = form.querySelector<HTMLButtonElement>('button[type="submit"]')
    const submitLabel = form.querySelector<HTMLElement>('[data-contact-submit-label]')
    const submitIcon = form.querySelector<SVGSVGElement>('[data-contact-submit-icon]')

    if (!submit || !submitLabel) {
        return
    }

    let submitting = false

    const renderErrors = (errors: Partial<Record<ContactField, string>>): void => {
        CONTACT_FIELDS.forEach((field) => {
            const slot = form.querySelector<HTMLElement>(`[data-contact-error="${field}"]`)
            const control = form.querySelector<HTMLElement>(`[name="${field}"]`)
            const message = errors[field]

            if (slot) {
                slot.textContent = message ?? ''

                if (message) {
                    show(slot)
                } else {
                    hide(slot)
                }
            }

            if (control) {
                control.setAttribute('aria-invalid', message ? 'true' : 'false')

                const describedBy = CONTACT_DESCRIBED[field]

                if (message && describedBy) {
                    control.setAttribute('aria-describedby', describedBy)
                } else {
                    control.removeAttribute('aria-describedby')
                }
            }
        })
    }

    const setSubmitting = (active: boolean): void => {
        submitting = active
        submit.disabled = active
        submitLabel.textContent = active ? 'Preparing message...' : 'Send message'

        if (submitIcon) {
            submitIcon.style.display = active ? 'none' : ''
        }
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault()

        if (submitting) {
            return
        }

        const data = new FormData(form)
        const values: Record<ContactField, string> = {
            name: String(data.get('name') ?? '').trim(),
            email: String(data.get('email') ?? '').trim(),
            subject: String(data.get('subject') ?? '').trim(),
            message: String(data.get('message') ?? '').trim(),
        }

        const errors: Partial<Record<ContactField, string>> = {}

        if (values.name.length < 2) {
            errors.name = 'Enter your name.'
        }

        if (!EMAIL_PATTERN.test(values.email)) {
            errors.email = 'Enter a valid email address.'
        }

        if (!values.subject) {
            errors.subject = 'Choose a topic.'
        }

        if (values.message.length < 20) {
            errors.message = 'Tell us a little more (at least 20 characters).'
        }

        renderErrors(errors)

        if (Object.keys(errors).length) {
            return
        }

        setSubmitting(true)

        void sendContactMessage(form, values)
            .then((result) => {
                if (result.ok) {
                    form.reset()
                    hide(form)

                    if (success) {
                        show(success)
                    }

                    return
                }

                renderErrors(result.errors)
            })
            .finally(() => setSubmitting(false))
    })

    const reset = document.querySelector<HTMLButtonElement>('[data-contact-reset]')

    reset?.addEventListener('click', () => {
        form.reset()
        renderErrors({})

        if (success) {
            hide(success)
        }

        show(form)
    })
}

function init(): void {
    /*
     * Each feature wires independently: a page missing a feature's hooks is
     * the normal case and costs nothing, and a feature that throws must not
     * take the rest of the page down with it.
     */
    const features: Array<[string, () => void]> = [
        ['product_dropdown', initProductDropdown],
        ['mobile_nav', initMobileNav],
        ['auth_modal', initAuthModal],
        ['faq', initFaq],
        ['auth_email_form', initAuthEmailForm],
        ['contact_form', initContactForm],
    ]

    features.forEach(([name, feature]) => {
        try {
            feature()
        } catch (error) {
            console.error(`carpdraw marketing: ${name} failed to initialise`, error)
        }
    })
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true })
} else {
    init()
}
