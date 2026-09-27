/**
 * The small windows the article editor asks questions through.
 *
 * Built rather than borrowed: this runs on a Blade marketing page with no Vue
 * on it, under `script-src 'self'`, and `window.prompt` cannot hold a textarea,
 * a preview or two fields at once. Everything here is one element appended to
 * <body>, removed on close, and resolved through a promise so the callers read
 * as the steps they are.
 */

/** One question on a form: a line, or a box for something longer. */
export interface DialogField {
    name: string
    label: string
    value?: string
    multiline?: boolean
    placeholder?: string
    /** Printed under the field - a limit, a format, a warning. */
    help?: string
}

interface DialogOptions {
    title: string
    fields?: DialogField[]
    /** Extra markup between the title and the buttons - the crop stage. */
    body?: HTMLElement
    confirmLabel?: string
    cancelLabel?: string
    /** Called with the panel once it is on the page, for wiring extras. */
    onOpen?: (panel: HTMLElement, close: (result: Record<string, string> | null) => void) => void
    destructive?: boolean
}

/**
 * Open a dialog and resolve with what was filled in, or with null if it was
 * dismissed. Escape and a click on the backdrop both dismiss.
 */
export function openDialog(options: DialogOptions): Promise<Record<string, string> | null> {
    return new Promise((resolve) => {
        const overlay = document.createElement('div')
        overlay.className = 'blog-editor-dialog'
        overlay.setAttribute('role', 'dialog')
        overlay.setAttribute('aria-modal', 'true')
        overlay.setAttribute('aria-label', options.title)

        const panel = document.createElement('div')
        panel.className = 'dialog-panel'
        overlay.appendChild(panel)

        const heading = document.createElement('h2')
        heading.textContent = options.title
        panel.appendChild(heading)

        if (options.body) {
            panel.appendChild(options.body)
        }

        const inputs = new Map<string, HTMLInputElement | HTMLTextAreaElement>()

        for (const field of options.fields ?? []) {
            const wrapper = document.createElement('p')
            wrapper.style.margin = '0 0 0.75rem'

            const label = document.createElement('label')
            label.textContent = field.label
            label.htmlFor = `blog-dialog-${field.name}`
            wrapper.appendChild(label)

            const input = field.multiline
                ? document.createElement('textarea')
                : document.createElement('input')

            if (input instanceof HTMLTextAreaElement) {
                input.rows = 6
            } else {
                input.type = 'text'
            }

            input.id = `blog-dialog-${field.name}`
            input.name = field.name
            input.value = field.value ?? ''
            input.placeholder = field.placeholder ?? ''
            wrapper.appendChild(input)

            if (field.help) {
                const help = document.createElement('span')
                help.className = 'dialog-help'
                help.textContent = field.help
                wrapper.appendChild(help)
            }

            inputs.set(field.name, input)
            panel.appendChild(wrapper)
        }

        const actions = document.createElement('div')
        actions.className = 'dialog-actions'
        panel.appendChild(actions)

        const cancel = document.createElement('button')
        cancel.type = 'button'
        cancel.className = 'dialog-button'
        cancel.textContent = options.cancelLabel ?? 'Cancel'
        actions.appendChild(cancel)

        const confirm = document.createElement('button')
        confirm.type = 'button'
        confirm.className = 'dialog-button dialog-button-primary'
        confirm.textContent = options.confirmLabel ?? 'Save'

        if (options.destructive) {
            confirm.dataset.destructive = 'true'
        }

        actions.appendChild(confirm)

        let settled = false

        const close = (result: Record<string, string> | null): void => {
            if (settled) {
                return
            }

            settled = true
            document.removeEventListener('keydown', onKeydown, true)
            overlay.remove()
            resolve(result)
        }

        const collect = (): Record<string, string> => {
            const values: Record<string, string> = {}

            for (const [name, input] of inputs) {
                values[name] = input.value
            }

            return values
        }

        function onKeydown(event: KeyboardEvent): void {
            if (event.key === 'Escape') {
                event.preventDefault()
                close(null)

                return
            }

            /*
             * Enter submits a one-line form and does nothing in a textarea,
             * where it is a newline the writer meant to type.
             */
            if (
                event.key === 'Enter' &&
                !(event.target instanceof HTMLTextAreaElement) &&
                overlay.contains(event.target as Node)
            ) {
                event.preventDefault()
                close(collect())
            }
        }

        cancel.addEventListener('click', () => close(null))
        confirm.addEventListener('click', () => close(collect()))
        overlay.addEventListener('mousedown', (event) => {
            if (event.target === overlay) {
                close(null)
            }
        })
        document.addEventListener('keydown', onKeydown, true)

        document.body.appendChild(overlay)
        options.onOpen?.(panel, close)

        const first = inputs.values().next().value

        if (first) {
            first.focus()
            first.select()
        } else {
            confirm.focus()
        }
    })
}

/** A yes-or-no, for the operations that cannot be undone. */
export async function confirmDialog(title: string, confirmLabel: string): Promise<boolean> {
    const result = await openDialog({ title, confirmLabel, destructive: true })

    return result !== null
}

/** One value, asked for in one line. Resolves null when dismissed. */
export async function promptDialog(title: string, field: DialogField): Promise<string | null> {
    const result = await openDialog({ title, fields: [field] })

    return result === null ? null : (result[field.name] ?? '')
}
