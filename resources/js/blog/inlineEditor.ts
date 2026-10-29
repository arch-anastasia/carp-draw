/**
 * The article editor, on the article.
 *
 * An admin opening any /blog/{slug} gets a toolbar; a reader never does, and
 * this module is only fetched on a page carrying one - see the dynamic import
 * in resources/js/marketing.ts - so nobody else downloads a byte of it.
 *
 * What it edits, and where:
 *
 *   the headline   in place, on the <h1>
 *   the prose      in place, block by block, with the formatting bar that
 *                  appears over a selection
 *   the pictures   by right-clicking one: replace, crop, describe, caption,
 *                  realign, reorder, remove
 *   the questions  in place, in the accordion that prints them
 *   everything     from the toolbar's panels - slug, excerpt, meta pair,
 *   else           byline, categories, the closing offer, the publish date
 *
 * Two rules run through the whole thing. Nothing saves by itself: edits collect
 * until Save, so a headline and a publish date changed together go up as one
 * edit. And the server has the last word on rendering: a save answers with the
 * article re-rendered from the markdown that was stored, and the editor swaps
 * its body for that - so a writer is looking at the published page, not at the
 * browser's approximation of it.
 */

import { readBlocks, serializeBody, blockGroupOf, type BlockSnapshot } from './htmlToMarkdown'
import { attachImageMenus, attachCoverMenu, chooseFile, type ImageContext } from './images'
import { openDialog, promptDialog } from './dialog'
import { icon, type IconName } from './icons'
import { closeMenu } from './menu'
import { closePanel, currentPanel, togglePanel, type InsertKind, type PanelName } from './panels'
import type { EditorBootstrap, EditorPost, FaqItem, SavePayload, UploadedImage } from './types'

/** Markdown the "Insert" panel drops in, ready to be edited into shape. */
const TEMPLATES: Partial<Record<InsertKind, string>> = {
    brief: ':::brief "Manufacturing brief"\nConfirm the dimensions, materials and tolerances.\n:::',
    steps: ':::steps "How to plan a drawing"\n### Start with a photo\nUse a clear photo of the furniture.\n\n### Check dimensions\nMeasure width, depth and height.\n:::',
    /* The second image shows the {.tryable} marker, which is the only place
       an editor would discover it: it puts a "Create a drawing" on the tile. */
    gallery:
        ':::gallery\n![Front view](/storage/blog/example.webp)\n![Side view](/storage/blog/example.webp){.tryable}\n:::',
    'try-on': ':::try-on\nCreate a drawing concept before you build.\n:::',
    youtube: ':::youtube dQw4w9WgXcQ "Planning a furniture drawing"\n:::',
    markdown: '',
}

const PANELS: PanelName[] = ['article', 'seo', 'publishing', 'insert']

/**
 * Rename an icon button without touching the mark inside it.
 *
 * The bar's buttons are an `<svg>` and a visually hidden name, so the old
 * `button.textContent = 'Done'` would have replaced the icon with the word.
 * Both faces of the name move together: the hidden span a screen reader
 * announces, and the `data-tip` the tooltip draws.
 */
function rename(button: HTMLElement | null, name: string, hook: string): void {
    if (button === null) {
        return
    }

    button.dataset.tip = name

    const hidden = button.querySelector<HTMLElement>(`[data-editor-${hook}-label]`)

    if (hidden !== null) {
        hidden.textContent = name
    }
}

export function initBlogEditor(): void {
    const host = document.querySelector<HTMLElement>('[data-blog-editor]')
    const body = document.querySelector<HTMLElement>('[data-blog-editor-body]')

    if (host === null || body === null || host.dataset.bound === 'true') {
        return
    }

    host.dataset.bound = 'true'

    let config: EditorBootstrap

    try {
        config = JSON.parse(host.dataset.blogEditor ?? '') as EditorBootstrap
    } catch {
        return
    }

    new Editor(host, body, config).attach()
}

class Editor {
    private post: EditorPost

    private pending: SavePayload = {}

    private snapshots: Map<string, BlockSnapshot>

    private bodyDirty = false

    private faqDirty = false

    private editing = false

    private saving = false

    /** Numbers the blocks this session adds, which have no server id yet. */
    private inserted = 0

    private readonly title: HTMLElement | null

    private readonly faq: HTMLElement | null

    private readonly hint: HTMLElement | null

    private readonly saveButton: HTMLButtonElement | null

    private readonly discardButton: HTMLButtonElement | null

    private readonly toggleButton: HTMLButtonElement | null

    private format: HTMLElement | null = null

    constructor(
        private readonly host: HTMLElement,
        private readonly body: HTMLElement,
        private readonly config: EditorBootstrap
    ) {
        this.post = config.post
        this.snapshots = readBlocks(body)
        this.title = document.querySelector('[data-blog-editor-title]')
        this.faq = document.querySelector('[data-blog-editor-faq]')
        this.hint = host.querySelector('[data-editor-hint]')
        this.saveButton = host.querySelector('[data-editor-save]')
        this.discardButton = host.querySelector('[data-editor-discard]')
        this.toggleButton = host.querySelector('[data-editor-toggle]')
    }

    attach(): void {
        const actions = this.host.querySelector<HTMLElement>('[data-editor-actions]')

        if (actions !== null) {
            actions.hidden = false
        }

        /*
         * The publish form is the no-JavaScript fallback and nothing else now:
         * with the bundle running, publishing is the Publishing panel's job,
         * where the date it would use is printed beside the button.
         */
        for (const fallback of Array.from(
            this.host.querySelectorAll<HTMLElement>('[data-editor-fallback]')
        )) {
            fallback.hidden = true
        }

        this.toggleButton?.addEventListener('click', () => this.setEditing(!this.editing))
        this.saveButton?.addEventListener('click', () => void this.save())
        this.discardButton?.addEventListener('click', () => this.discard())

        for (const name of PANELS) {
            const button = this.host.querySelector<HTMLElement>(
                `[data-editor-panel-open="${name}"]`
            )

            button?.addEventListener('click', () => togglePanel(name, button, this.panelContext()))
        }

        this.body.addEventListener('input', () => this.markBody())
        this.body.addEventListener('click', (event) => this.interceptLinks(event))
        this.body.addEventListener('dblclick', (event) => void this.editSource(event))

        /*
         * The headline is collected as it is typed, not only on the way out:
         * without this a writer retitles an article and watches Save stay
         * greyed out, because nothing else about the page has changed.
         */
        this.title?.addEventListener('input', () => {
            this.collectTitle()
            this.refreshButtons()
        })

        attachImageMenus(this.imageContext())

        this.attachFaq()
        this.attachFormatBar()

        /*
         * Publishing navigates. Doing that with edits still in the buffer would
         * publish the article as it was stored, throw away what is on screen,
         * and look from the outside like the save went in.
         */
        this.host
            .querySelector<HTMLFormElement>('[data-editor-publish-form]')
            ?.addEventListener('submit', (event) => {
                if (this.isDirty()) {
                    event.preventDefault()
                    this.notify('Save your changes before publishing.', 'error')
                }
            })

        document.addEventListener('keydown', (event) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
                event.preventDefault()
                void this.save()
            }
        })

        window.addEventListener('beforeunload', (event) => {
            if (this.isDirty()) {
                event.preventDefault()
                event.returnValue = ''
            }
        })

        this.refreshButtons()
    }

    /* ------------------------------------------------------------ editing */

    private setEditing(editing: boolean): void {
        if (this.editing === editing) {
            return
        }

        this.editing = editing

        this.body.contentEditable = editing ? 'true' : 'false'
        this.body.spellcheck = editing

        if (this.title !== null) {
            /*
             * plaintext-only where it exists: a headline is one line of text,
             * and a pasted <h2> inside an <h1> is not something to store. Where
             * it does not, the value is read as textContent anyway.
             */
            this.title.contentEditable = editing ? 'plaintext-only' : 'false'

            if (editing && this.title.contentEditable !== 'plaintext-only') {
                this.title.contentEditable = 'true'
            }
        }

        this.faq?.classList.toggle('blog-faq-editing', editing)

        for (const field of this.faqFields()) {
            field.contentEditable = editing ? 'true' : 'false'
        }

        const controls = this.faq?.querySelector<HTMLElement>('[data-faq-controls]')

        if (controls) {
            controls.hidden = !editing
        }

        this.toggleButton?.setAttribute('aria-pressed', editing ? 'true' : 'false')
        rename(this.toggleButton, editing ? 'Stop editing' : 'Edit the article', 'toggle')

        /*
         * Inserting a block turns editing on as a side effect, so the button
         * that does it only appears once editing is on and the click means
         * what it says.
         */
        for (const control of Array.from(
            this.host.querySelectorAll<HTMLElement>('[data-editor-while-editing]')
        )) {
            control.hidden = !editing
        }

        if (!editing) {
            this.hideFormatBar()
            closeMenu()

            if (currentPanel() === 'insert') {
                closePanel()
            }
        }
    }

    private markBody(): void {
        this.bodyDirty = true
        this.refreshButtons()
    }

    /** A link inside the article is a destination for readers, not for writers. */
    private interceptLinks(event: MouseEvent): void {
        if (!this.editing || event.metaKey || event.ctrlKey) {
            return
        }

        const anchor = (event.target as Element | null)?.closest?.('a')

        if (anchor !== null && anchor !== undefined && this.body.contains(anchor)) {
            event.preventDefault()
        }
    }

    /**
     * A directive block has no HTML to type into - it renders as a widget - so
     * double-clicking one opens the markdown behind it instead.
     */
    private async editSource(event: MouseEvent): Promise<void> {
        if (!this.editing) {
            return
        }

        const block = (event.target as Element | null)?.closest?.('[data-md-atomic]')

        if (!(block instanceof HTMLElement)) {
            return
        }

        const id = block.getAttribute('data-md-block')
        const snapshot = id === null ? undefined : this.snapshots.get(id)

        if (id === null || snapshot === undefined) {
            return
        }

        event.preventDefault()

        const answer = await openDialog({
            title: 'Edit block',
            fields: [
                {
                    name: 'source',
                    label: 'Markdown',
                    value: snapshot.source,
                    multiline: true,
                    help: 'Rendered by the server when you save.',
                },
            ],
        })

        if (answer === null) {
            return
        }

        const source = answer.source.trim()

        if (source === '') {
            block.remove()
            this.snapshots.delete(id)
            this.markBody()

            return
        }

        this.snapshots.set(id, { ...snapshot, source })
        this.markBody()
        this.notify('Block updated. Save to render it.')
    }

    /* ------------------------------------------------------------- the FAQ */

    private attachFaq(): void {
        this.faq?.addEventListener('input', () => {
            this.faqDirty = true
            this.refreshButtons()
        })

        this.faq
            ?.querySelector<HTMLButtonElement>('[data-faq-add]')
            ?.addEventListener('click', () => void this.addQuestion())
    }

    private async addQuestion(): Promise<void> {
        const answer = await openDialog({
            title: 'Add a question',
            fields: [
                { name: 'question', label: 'Question' },
                { name: 'answer', label: 'Answer', multiline: true },
            ],
        })

        if (answer === null || answer.question.trim() === '') {
            return
        }

        this.renderFaq([
            ...this.collectFaq(),
            { question: answer.question.trim(), answer: answer.answer.trim() },
        ])

        this.faqDirty = true
        this.refreshButtons()
    }

    private faqFields(): HTMLElement[] {
        return Array.from(
            this.faq?.querySelectorAll<HTMLElement>('[data-faq-question], [data-faq-answer]') ?? []
        )
    }

    private collectFaq(): FaqItem[] {
        return Array.from(this.faq?.querySelectorAll<HTMLElement>('[data-faq-item]') ?? [])
            .map((item) => ({
                question: item.querySelector('[data-faq-question]')?.textContent?.trim() ?? '',
                answer: item.querySelector('[data-faq-answer]')?.textContent?.trim() ?? '',
            }))
            .filter((item) => item.question !== '' && item.answer !== '')
    }

    /** Rebuild the accordion, keeping the markup the Blade component prints. */
    private renderFaq(items: FaqItem[]): void {
        const list = this.faq?.querySelector('[data-faq-list]')

        if (!list) {
            return
        }

        list.replaceChildren()

        items.forEach((item, index) => {
            const wrapper = document.createElement('div')
            wrapper.className = 'collapse collapse-plus rounded-none border-b border-base-300'
            wrapper.setAttribute('data-faq-item', '')

            const radio = document.createElement('input')
            radio.type = 'radio'
            radio.name = 'post-faq-accordion'
            radio.setAttribute('aria-label', item.question)
            radio.checked = index === 0
            wrapper.appendChild(radio)

            const question = document.createElement('div')
            question.className = 'collapse-title px-0 text-[17px] font-normal text-base-content'
            question.setAttribute('data-faq-question', '')
            question.contentEditable = this.editing ? 'true' : 'false'
            question.textContent = item.question
            wrapper.appendChild(question)

            const content = document.createElement('div')
            content.className = 'collapse-content px-0'

            const answer = document.createElement('p')
            answer.className = 'pt-1 text-[15px] leading-relaxed text-base-content/65'
            answer.setAttribute('data-faq-answer', '')
            answer.contentEditable = this.editing ? 'true' : 'false'
            answer.textContent = item.answer
            content.appendChild(answer)
            wrapper.appendChild(content)

            list.appendChild(wrapper)
        })
    }

    /* --------------------------------------------------------- formatting */

    /**
     * The bar over a selection: bold, italic, a link, the two heading levels,
     * lists and a quote.
     *
     * `document.execCommand` is deprecated and still the only rich-text
     * primitive a browser offers without an editor framework behind it. This is
     * an admin tool on a Blade page with no Vue on it, and pulling in a
     * document model to make six buttons work would be the larger mistake.
     */
    private attachFormatBar(): void {
        document.addEventListener('selectionchange', () => {
            if (!this.editing) {
                return
            }

            const selection = window.getSelection()
            const range = selection?.rangeCount === 0 ? null : selection?.getRangeAt(0)

            if (
                !selection ||
                !range ||
                selection.isCollapsed ||
                !this.body.contains(range.commonAncestorContainer)
            ) {
                this.hideFormatBar()

                return
            }

            this.showFormatBar(range.getBoundingClientRect())
        })
    }

    private showFormatBar(bounds: DOMRect): void {
        if (this.format === null) {
            this.format = document.createElement('div')
            this.format.className = 'blog-format-bar'

            /*
             * B, I and the heading levels stay as letters, because that is
             * what every writing tool has drawn them as for thirty years. The
             * rest were an emoji chain link and a multiplication sign standing
             * in for icons, and are icons now.
             */
            const commands: Array<{
                letters?: string
                mark?: IconName
                title: string
                run: () => void
            }> = [
                { letters: 'B', title: 'Bold', run: () => document.execCommand('bold') },
                { letters: 'I', title: 'Italic', run: () => document.execCommand('italic') },
                {
                    letters: 'H2',
                    title: 'Heading',
                    run: () => document.execCommand('formatBlock', false, 'h2'),
                },
                {
                    letters: 'H3',
                    title: 'Subheading',
                    run: () => document.execCommand('formatBlock', false, 'h3'),
                },
                {
                    letters: 'P',
                    title: 'Paragraph',
                    run: () => document.execCommand('formatBlock', false, 'p'),
                },
                {
                    mark: 'quote',
                    title: 'Quote',
                    run: () => document.execCommand('formatBlock', false, 'blockquote'),
                },
                {
                    mark: 'bullets',
                    title: 'Bullets',
                    run: () => document.execCommand('insertUnorderedList'),
                },
                {
                    mark: 'numbers',
                    title: 'Numbers',
                    run: () => document.execCommand('insertOrderedList'),
                },
                { mark: 'link', title: 'Link', run: () => void this.link() },
                {
                    mark: 'clear',
                    title: 'Clear formatting',
                    run: () => document.execCommand('removeFormat'),
                },
            ]

            for (const command of commands) {
                const button = document.createElement('button')
                button.type = 'button'
                button.title = command.title
                button.setAttribute('aria-label', command.title)

                if (command.mark === undefined) {
                    button.textContent = command.letters ?? ''
                } else {
                    button.appendChild(icon(command.mark))
                }

                /* Mousedown, not click: a click would drop the selection first. */
                button.addEventListener('mousedown', (event) => {
                    event.preventDefault()
                    command.run()
                    this.markBody()
                })
                this.format.appendChild(button)
            }

            document.body.appendChild(this.format)
        }

        this.format.hidden = false
        this.format.style.left = `${Math.max(8, bounds.left + window.scrollX)}px`
        this.format.style.top = `${bounds.top + window.scrollY - this.format.offsetHeight - 8}px`
    }

    private hideFormatBar(): void {
        if (this.format !== null) {
            this.format.hidden = true
        }
    }

    private async link(): Promise<void> {
        const selection = window.getSelection()
        const range = selection?.rangeCount ? selection.getRangeAt(0).cloneRange() : null

        const href = await promptDialog('Link to', {
            name: 'href',
            label: 'URL',
            placeholder: '/blog/another-guide',
        })

        if (href === null || range === null) {
            return
        }

        selection?.removeAllRanges()
        selection?.addRange(range)

        if (href.trim() === '') {
            document.execCommand('unlink')
        } else {
            document.execCommand('createLink', false, href.trim())
        }

        this.markBody()
    }

    /* ------------------------------------------------------------ inserts */

    private async insert(kind: InsertKind): Promise<void> {
        this.setEditing(true)

        if (kind === 'image') {
            const file = await chooseFile()

            if (file === null) {
                return
            }

            const alt = await promptDialog('Describe the picture', {
                name: 'alt',
                label: 'Alt text',
                help: 'What the picture shows, for a reader who cannot see it.',
            })

            const uploaded = await this.upload(file, alt ?? '')

            if (uploaded !== null) {
                this.place(this.pictureBlock(uploaded))
            }

            return
        }

        const template = TEMPLATES[kind]

        if (template !== undefined) {
            const answer = await openDialog({
                title: 'Insert block',
                fields: [
                    {
                        name: 'source',
                        label: 'Markdown',
                        value: template,
                        multiline: true,
                        help: 'Rendered by the server when you save.',
                    },
                ],
            })

            if (answer === null || answer.source.trim() === '') {
                return
            }

            this.place(this.atomicBlock(answer.source.trim()))

            return
        }

        this.place(this.proseBlock(kind))
    }

    private proseBlock(kind: InsertKind): HTMLElement {
        const element = document.createElement(
            kind === 'h2' || kind === 'style-entry'
                ? 'h2'
                : kind === 'h3'
                  ? 'h3'
                  : kind === 'ul'
                    ? 'ul'
                    : kind === 'ol'
                      ? 'ol'
                      : kind === 'quote'
                        ? 'blockquote'
                        : kind === 'code'
                          ? 'pre'
                          : kind === 'divider'
                            ? 'hr'
                            : 'p'
        )

        if (kind === 'ul' || kind === 'ol') {
            const item = document.createElement('li')
            item.textContent = 'Something worth listing'
            element.appendChild(item)
        } else if (kind === 'code') {
            const code = document.createElement('code')
            code.textContent = 'code here'
            element.appendChild(code)
        } else if (kind === 'style-entry') {
            /*
             * A style entry owns the anchor the "jump to a hairstyle" list and
             * the ItemList schema both point at, so it is written back with its
             * directive - see htmlToMarkdown's headingToMarkdown().
             */
            const span = document.createElement('span')
            span.id = `entry-${++this.inserted}`
            span.className = 'scroll-mt-24'
            span.textContent = 'A hairstyle worth its own entry'
            element.appendChild(span)
        } else if (kind !== 'divider') {
            element.textContent = kind === 'quote' ? 'Something worth quoting' : 'A new paragraph'
        }

        return this.markInserted(element)
    }

    private pictureBlock(uploaded: UploadedImage): HTMLElement {
        const picture = document.createElement('picture')
        const image = document.createElement('img')

        image.src = uploaded.url
        image.alt = uploaded.alt ?? ''
        image.className = 'block mx-auto my-8 w-full rounded-box border border-base-300'
        image.loading = 'lazy'
        image.decoding = 'async'
        image.dataset.mdHint = 'default'

        if (uploaded.width !== null && uploaded.height !== null) {
            image.width = uploaded.width
            image.height = uploaded.height
        }

        picture.appendChild(image)

        return this.markInserted(picture)
    }

    /**
     * A block the browser cannot render: its markdown is kept as the block's
     * source and printed as-is until the next save, when the server renders it
     * and hands the real HTML back.
     */
    private atomicBlock(source: string): HTMLElement {
        const element = document.createElement('div')
        element.className = 'blog-pending-block'
        element.contentEditable = 'false'
        element.setAttribute('data-md-atomic', '1')
        element.textContent = source

        const marked = this.markInserted(element)
        const id = marked.getAttribute('data-md-block') as string

        this.snapshots.set(id, { source, html: '', atomic: true })

        return marked
    }

    /**
     * Give a new block an id of its own, so it is serialised on its own rather
     * than being swallowed by the block it was dropped after.
     */
    private markInserted(element: HTMLElement): HTMLElement {
        element.setAttribute('data-md-block', `new-${++this.inserted}`)

        return element
    }

    /** Drop a block in after the one the caret is in, or at the end. */
    private place(element: HTMLElement): void {
        const selection = window.getSelection()
        const anchor =
            selection && selection.rangeCount > 0
                ? blockGroupOf(this.body, selection.getRangeAt(0).startContainer)
                : null

        if (anchor === null) {
            this.body.appendChild(element)
        } else {
            ;(anchor.nodes[anchor.nodes.length - 1] as ChildNode).after(element)
        }

        this.markBody()
        element.scrollIntoView?.({ block: 'center', behavior: 'smooth' })
    }

    /* ------------------------------------------------------------- covers */

    private imageContext(): ImageContext {
        return {
            body: this.body,
            upload: (file, alt) => this.upload(file, alt),
            markBodyDirty: () => this.markBody(),
            setCover: (image) => this.setCover(image),
            startEditing: () => this.setEditing(true),
            notify: (message, tone) => this.notify(message, tone),
        }
    }

    private setCover(image: UploadedImage | null): void {
        this.pending.cover_image_path = image === null ? null : image.url
        if (image === null) {
            delete this.pending.cover_image_alt
        } else {
            this.pending.cover_image_alt = image.alt
        }

        const existing = document.querySelector<HTMLImageElement>('[data-blog-editor-cover]')
        const slot = document.querySelector<HTMLElement>('[data-blog-editor-cover-empty]')

        if (image === null) {
            existing?.remove()

            if (slot) {
                slot.hidden = false
            }

            this.refreshButtons()

            return
        }

        if (existing !== null) {
            existing.src = image.url
            existing.removeAttribute('srcset')
            if (image.width && image.height) {
                existing.width = image.width
                existing.height = image.height
            } else {
                existing.removeAttribute('width')
                existing.removeAttribute('height')
            }

            if (image.alt !== null && image.alt !== '') {
                existing.alt = image.alt
            }
        } else if (slot !== null) {
            const created = document.createElement('img')
            created.setAttribute('data-blog-editor-cover', '')
            created.src = image.url
            created.alt = image.alt ?? ''
            if (image.width && image.height) {
                created.width = image.width
                created.height = image.height
            }
            created.className = 'mt-8 aspect-[3/2] w-full rounded-box object-cover'
            attachCoverMenu(this.imageContext(), created)
            slot.insertAdjacentElement('afterend', created)
            slot.hidden = true
        }

        this.refreshButtons()
    }

    /* ------------------------------------------------------------- saving */

    private isDirty(): boolean {
        return this.bodyDirty || this.faqDirty || Object.keys(this.pending).length > 0
    }

    /**
     * Save and Discard are worth two marks on the bar only while there is
     * something to save, so they arrive with the first edit and leave with the
     * save. They stay disabled as well as hidden: Cmd-S reaches Save from the
     * keyboard whether or not it is on screen, and a disabled button refuses
     * that too.
     */
    private refreshButtons(): void {
        const dirty = this.isDirty()

        if (this.saveButton !== null) {
            this.saveButton.disabled = !dirty || this.saving
            rename(this.saveButton, this.saving ? 'Saving…' : 'Save changes', 'save')
        }

        if (this.discardButton !== null) {
            this.discardButton.disabled = !dirty || this.saving
        }

        for (const group of Array.from(
            this.host.querySelectorAll<HTMLElement>('[data-editor-commit]')
        )) {
            group.hidden = !dirty && !this.saving
        }
    }

    /**
     * An emptied headline is not a headline, and one typed back to what it was
     * is not a change - both drop out of the pending set rather than being sent
     * for the server to refuse or to store as a no-op.
     */
    private collectTitle(): void {
        const typed = this.title?.textContent?.trim()

        if (typed === undefined) {
            return
        }

        if (typed === '' || typed === this.post.title) {
            delete this.pending.title

            return
        }

        this.pending.title = typed
    }

    private async save(): Promise<void> {
        this.collectTitle()

        if (!this.isDirty() || this.saving) {
            return
        }

        this.saving = true
        this.refreshButtons()
        this.notify('Saving…')

        const payload: SavePayload = { ...this.pending }

        if (this.bodyDirty) {
            payload.body = serializeBody(this.body, this.snapshots)
        }

        if (this.faqDirty) {
            payload.faq = this.collectFaq()
        }

        try {
            const response = await fetch(this.config.endpoints.save, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.config.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            })

            if (response.status === 422) {
                const errors = (await response.json()) as { message?: string }
                this.notify(errors.message ?? 'That did not validate.', 'error')

                return
            }

            if (!response.ok) {
                this.notify(`The save failed (${response.status}). Nothing was changed.`, 'error')

                return
            }

            this.apply((await response.json()) as { post: EditorPost; html: string })
        } catch {
            this.notify('The save could not reach the server. Nothing was changed.', 'error')
        } finally {
            this.saving = false
            this.refreshButtons()
        }
    }

    /** Take the server's rendering of what was just stored. */
    private apply(result: { post: EditorPost; html: string }): void {
        const previousSlug = this.post.slug

        this.post = result.post
        this.pending = {}
        this.bodyDirty = false
        this.faqDirty = false

        this.body.innerHTML = result.html
        document.dispatchEvent(new Event('marketing:content-updated'))
        this.snapshots = readBlocks(this.body)

        if (this.title !== null) {
            this.title.textContent = this.post.title
        }

        this.renderFaq(this.post.faq)
        this.paintStatus()

        /*
         * A renamed slug renames the page. Replacing the address means a reload
         * lands on the article rather than on a 404 of its old URL - and the
         * old one really is gone, so it is the honest thing to show.
         */
        if (this.post.slug !== previousSlug) {
            window.history.replaceState({}, '', this.post.url)
            this.notify('Saved. The URL changed - add a redirect for the old one.')
        } else {
            this.notify('Saved.')
        }

        this.refreshButtons()
    }

    private paintStatus(): void {
        const badge = this.host.querySelector<HTMLElement>('[data-editor-badge]')
        const message = this.host.querySelector<HTMLElement>('[data-editor-message]')

        if (badge !== null) {
            badge.dataset.status = this.post.status
            badge.textContent =
                this.post.status === 'published'
                    ? 'Live'
                    : this.post.status === 'scheduled'
                      ? 'Scheduled'
                      : 'Draft'
        }

        /*
         * A date, or the fact that there is not one yet. What being
         * unpublished means is a sentence, and it is in the Publishing panel
         * rather than on a bar that has to fit beside everything else.
         */
        if (message !== null) {
            message.textContent =
                this.post.status === 'published'
                    ? `Published ${this.post.publishedAtLabel ?? ''}`
                    : this.post.status === 'scheduled'
                      ? `Publishes ${this.post.publishedAtLabel ?? ''}`
                      : 'Not published'
        }
    }

    private discard(): void {
        if (!this.isDirty()) {
            return
        }

        /*
         * A reload, not an undo stack. Everything unsaved lives in the DOM, and
         * the page as the server last rendered it is one request away.
         */
        this.pending = {}
        this.bodyDirty = false
        this.faqDirty = false
        window.location.reload()
    }

    private async upload(file: Blob, alt: string): Promise<UploadedImage | null> {
        const form = new FormData()
        form.append('file', file, file instanceof File ? file.name : 'image.webp')

        if (alt !== '') {
            form.append('alt', alt)
        }

        this.notify('Uploading…')

        try {
            const response = await fetch(this.config.endpoints.image, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.config.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: form,
            })

            if (!response.ok) {
                const problem = (await response.json().catch(() => ({}))) as { message?: string }
                this.notify(problem.message ?? 'That picture was refused.', 'error')

                return null
            }

            const uploaded = (await response.json()) as { data: UploadedImage }
            this.notify('Picture uploaded. Save to keep it.')

            return uploaded.data
        } catch {
            this.notify('The upload could not reach the server.', 'error')

            return null
        }
    }

    private notify(message: string, tone?: 'error'): void {
        if (this.hint === null) {
            return
        }

        this.hint.hidden = false
        this.hint.textContent = message

        if (tone === undefined) {
            this.hint.removeAttribute('data-tone')
        } else {
            this.hint.dataset.tone = tone
        }
    }

    private panelContext() {
        return {
            bootstrap: this.config,
            current: (): EditorPost => this.post,
            pending: (): SavePayload => this.pending,
            setField: <K extends keyof SavePayload>(field: K, value: SavePayload[K]): void => {
                this.pending[field] = value
                this.refreshButtons()
            },
            insert: (kind: InsertKind): void => void this.insert(kind),
            uploadCover: async (file: Blob): Promise<void> => {
                const uploaded = await this.upload(file, this.post.coverAlt ?? '')

                if (uploaded !== null) {
                    this.setCover(uploaded)
                }
            },
            removeCover: (): void => this.setCover(null),
            destroy: (): void => this.destroy(),
            approveRevision: (): void => {
                const pending = this.config.revision

                if (pending !== null) {
                    this.submitForm(pending.applyUrl)
                }
            },
            save: (): void => {
                closePanel()
                void this.save()
            },
        }
    }

    private destroy(): void {
        this.submitForm(this.config.endpoints.destroy, 'DELETE')
    }

    /**
     * The operations that are not edits: deleting the article, approving a
     * pending rewrite. Both are session-and-CSRF POSTs that redirect, so they
     * go up as a real form submission rather than as fetch - the answer to
     * either is a different page, and that is the browser's job.
     */
    private submitForm(action: string, method?: string): void {
        const form = document.createElement('form')
        form.method = 'POST'
        form.action = action
        form.hidden = true

        const fields: Array<[string, string]> = [['_token', this.config.csrf]]

        if (method !== undefined) {
            fields.push(['_method', method])
        }

        for (const [name, value] of fields) {
            const field = document.createElement('input')
            field.type = 'hidden'
            field.name = name
            field.value = value
            form.appendChild(field)
        }

        /* Whatever is unsaved is about to be irrelevant; do not warn about it. */
        this.pending = {}
        this.bodyDirty = false
        this.faqDirty = false

        document.body.appendChild(form)
        form.submit()
    }
}
