/**
 * The panels behind the toolbar: everything about an article that is not
 * written into the page itself.
 *
 * The prose, the headline, the pictures and the questions are edited where they
 * are printed, because that is where a writer can see them. What is left is the
 * things a reader never sees on the page - the slug, the excerpt, the meta
 * pair, the byline, the categories, the closing offer, the publish date - and
 * they live here.
 *
 * Three of them, not five. "Ending" held two fields about a button and sat on
 * the bar as a word of its own; it is a section of the article panel now,
 * under the article it closes. Publishing absorbed the bar's publish button,
 * which was the same click with less information around it.
 *
 * A panel closes the way anything on top of a page should: Escape, the cross
 * in its corner, its own button on the bar again, or a click anywhere else.
 *
 * Nothing here saves on its own. A field writes into the pending change set and
 * the toolbar's Save button sends it, so a slug and a publish date changed
 * together go up as one edit.
 */

import { chooseFile } from './images'
import { openDialog } from './dialog'
import { icon, type IconName } from './icons'
import type { EditorBootstrap, EditorPost, SavePayload } from './types'

export interface PanelContext {
    bootstrap: EditorBootstrap
    /** The article as last saved, plus whatever is pending. */
    current: () => EditorPost
    pending: () => SavePayload
    setField: <K extends keyof SavePayload>(field: K, value: SavePayload[K]) => void
    insert: (kind: InsertKind) => void
    uploadCover: (file: Blob) => Promise<void>
    removeCover: () => void
    destroy: () => void
    approveRevision: () => void
    save: () => void
}

export type InsertKind =
    | 'image'
    | 'h2'
    | 'h3'
    | 'style-entry'
    | 'paragraph'
    | 'ul'
    | 'ol'
    | 'quote'
    | 'code'
    | 'divider'
    | 'brief'
    | 'steps'
    | 'gallery'
    | 'try-on'
    | 'youtube'
    | 'markdown'

export type PanelName = 'article' | 'seo' | 'publishing' | 'insert'

let openPanelName: PanelName | null = null
let panel: HTMLElement | null = null
/** Undoes the listeners that dismiss the open panel. Null while none is open. */
let releaseDismiss: (() => void) | null = null

export function closePanel(): void {
    releaseDismiss?.()
    releaseDismiss = null
    panel?.remove()
    panel = null
    openPanelName = null

    for (const button of Array.from(
        document.querySelectorAll<HTMLElement>('[data-editor-panel-open]')
    )) {
        button.setAttribute('aria-expanded', 'false')
    }
}

export function currentPanel(): PanelName | null {
    return openPanelName
}

/** Open one panel, closing whatever was open. Opening the same one closes it. */
export function togglePanel(name: PanelName, anchor: HTMLElement, context: PanelContext): void {
    if (openPanelName === name) {
        closePanel()

        return
    }

    closePanel()

    panel = document.createElement('div')
    panel.className = 'blog-editor-panel'
    panel.dataset.panel = name
    panel.setAttribute('role', 'dialog')
    panel.setAttribute('aria-label', TITLES[name])

    const header = document.createElement('div')
    header.className = 'panel-header'

    const heading = document.createElement('h2')
    heading.textContent = TITLES[name]
    header.appendChild(heading)

    const dismiss = document.createElement('button')
    dismiss.type = 'button'
    dismiss.className = 'panel-close'
    dismiss.setAttribute('aria-label', 'Close')
    dismiss.appendChild(icon('close'))
    dismiss.addEventListener('click', () => {
        closePanel()
        anchor.focus()
    })
    header.appendChild(dismiss)

    panel.appendChild(header)

    BUILDERS[name](panel, context)

    document.body.appendChild(panel)
    openPanelName = name
    anchor.setAttribute('aria-expanded', 'true')

    /* Docked to the toolbar, and pulled back inside the viewport if it overflows. */
    const bar = anchor.closest('.blog-editor-bar')?.getBoundingClientRect()

    if (bar) {
        const bounds = panel.getBoundingClientRect()
        panel.style.left = `${Math.max(8, Math.min(bar.left, window.innerWidth - bounds.width - 8))}px`
        panel.style.bottom = `${Math.max(8, window.innerHeight - bar.top + 8)}px`
    }

    armDismiss(anchor)

    panel.querySelector<HTMLElement>('input, textarea, select, .panel-insert')?.focus()
}

/**
 * Escape, or a pointer landing anywhere that is not the panel.
 *
 * The toolbar is exempt because its buttons already toggle the panel
 * themselves - dismissing on their `pointerdown` would close the panel a
 * fraction before their `click` reopened it. So are the dialogs and menus a
 * panel opens, which sit above it and outlive the click that opened them.
 */
function armDismiss(anchor: HTMLElement): void {
    const KEPT_OPEN_BY =
        '.blog-editor-panel, .blog-editor-bar, .blog-editor-dialog, .blog-image-menu, .blog-format-bar'

    const onPointerDown = (event: Event): void => {
        const target = event.target
        const element =
            target instanceof Element ? target : ((target as Node | null)?.parentElement ?? null)

        if (element === null || element.closest(KEPT_OPEN_BY) === null) {
            closePanel()
        }
    }

    const onKeydown = (event: KeyboardEvent): void => {
        if (event.key === 'Escape') {
            event.preventDefault()
            closePanel()
            anchor.focus()
        }
    }

    document.addEventListener('pointerdown', onPointerDown, true)
    document.addEventListener('keydown', onKeydown, true)

    releaseDismiss = (): void => {
        document.removeEventListener('pointerdown', onPointerDown, true)
        document.removeEventListener('keydown', onKeydown, true)
    }
}

const TITLES: Record<PanelName, string> = {
    article: 'Article',
    seo: 'Search and social',
    publishing: 'Publishing',
    insert: 'Insert a block',
}

const BUILDERS: Record<PanelName, (panel: HTMLElement, context: PanelContext) => void> = {
    article: buildArticle,
    seo: buildSeo,
    publishing: buildPublishing,
    insert: buildInsert,
}

/** A rule and a name over a run of fields, for the panels that hold two jobs. */
function section(panel: HTMLElement, title: string): void {
    const heading = document.createElement('p')
    heading.className = 'panel-group'
    heading.textContent = title
    panel.appendChild(heading)
}

function buildArticle(panel: HTMLElement, context: PanelContext): void {
    const post = context.current()

    panel.appendChild(
        textField({
            label: 'Headline',
            value: post.title,
            help: 'Also editable straight on the page.',
            onInput: (value) => context.setField('title', value),
        })
    )

    panel.appendChild(
        textField({
            label: 'Slug',
            value: post.slug,
            help: 'This is the URL. Changing it moves the page - old links stop working unless a redirect is added.',
            onInput: (value) => context.setField('slug', value),
        })
    )

    panel.appendChild(
        textField({
            label: 'Excerpt',
            value: post.excerpt ?? '',
            multiline: true,
            help: `The summary on the blog index and in search results. Up to ${context.bootstrap.limits.excerpt} characters.`,
            counter: context.bootstrap.limits.excerpt,
            onInput: (value) => context.setField('excerpt', value),
        })
    )

    section(panel, 'Filed under')

    panel.appendChild(
        selectField({
            label: 'Audience',
            value: post.audience ?? '',
            options: [
                {
                    value: '',
                    label: `Inherit from category (${post.resolvedAudience ?? 'neutral'})`,
                },
                ...context.bootstrap.options.audiences,
            ],
            onChange: (value) => context.setField('audience', value === '' ? null : value),
        })
    )

    panel.appendChild(
        checkboxes({
            label: 'Categories',
            options: context.bootstrap.options.categories,
            selected: pendingList(context, 'categories') ?? post.categories,
            onChange: (values) => context.setField('categories', values),
        })
    )

    panel.appendChild(
        checkboxes({
            label: 'Byline',
            options: context.bootstrap.options.authors,
            selected: pendingList(context, 'authors') ?? post.authors,
            help: 'Nobody selected credits the house account rather than publishing unsigned.',
            onChange: (values) => context.setField('authors', values),
        })
    )

    buildEnding(panel, context)
}

function buildSeo(panel: HTMLElement, context: PanelContext): void {
    const post = context.current()

    panel.appendChild(
        textField({
            label: 'Meta title',
            value: post.metaTitle ?? '',
            placeholder: post.title,
            help: 'Empty falls back to the headline.',
            counter: context.bootstrap.limits.metaTitle,
            onInput: (value) => context.setField('meta_title', value),
        })
    )

    panel.appendChild(
        textField({
            label: 'Meta description',
            value: post.metaDescription ?? '',
            placeholder: post.excerpt ?? '',
            multiline: true,
            help: 'Empty falls back to the excerpt.',
            counter: context.bootstrap.limits.metaDescription,
            onInput: (value) => context.setField('meta_description', value),
        })
    )

    panel.appendChild(
        textField({
            label: 'Target keyword',
            value: post.targetKeyword ?? '',
            help: 'The one phrase this article is meant to win.',
            onInput: (value) => context.setField('target_keyword', value),
        })
    )

    panel.appendChild(
        textField({
            label: 'Secondary keywords',
            value: (post.secondaryKeywords ?? []).join(', '),
            help: 'Comma separated.',
            onInput: (value) =>
                context.setField(
                    'secondary_keywords',
                    value
                        .split(',')
                        .map((keyword) => keyword.trim())
                        .filter((keyword) => keyword !== '')
                ),
        })
    )

    section(panel, 'Cover image')

    const cover = document.createElement('div')
    cover.className = 'panel-field'

    const note = document.createElement('p')
    note.className = 'panel-help'
    note.textContent = post.coverUrl
        ? 'Right-click the cover on the page to crop it or change its alt text.'
        : 'No cover. The article shares without a picture until it has one.'
    cover.appendChild(note)

    const actions = document.createElement('div')
    actions.className = 'panel-actions'

    actions.appendChild(
        button(post.coverUrl ? 'Replace cover' : 'Upload cover', () => {
            void chooseFile().then((file) => {
                if (file !== null) {
                    void context.uploadCover(file)
                }
            })
        })
    )

    if (post.coverUrl) {
        actions.appendChild(button('Remove cover', () => context.removeCover(), 'destructive'))
    }

    cover.appendChild(actions)
    panel.appendChild(cover)
}

/**
 * The offer an article closes on - a section of the article panel rather than
 * a panel of its own, which is what two fields about one button had been.
 */
function buildEnding(panel: HTMLElement, context: PanelContext): void {
    const post = context.current()

    section(panel, 'The ending')

    const note = document.createElement('p')
    note.className = 'panel-help'
    note.textContent =
        'Both halves or neither. Leave them empty and the article closes on the house offer from config/blog.php.'
    panel.appendChild(note)

    panel.appendChild(
        textField({
            label: 'Button text',
            value: post.ctaText ?? '',
            onInput: (value) => context.setField('cta_text', value),
        })
    )

    panel.appendChild(
        textField({
            label: 'Button URL',
            value: post.ctaUrl ?? '',
            placeholder: '/',
            onInput: (value) => context.setField('cta_url', value),
        })
    )
}

function buildPublishing(panel: HTMLElement, context: PanelContext): void {
    const post = context.current()

    const status = document.createElement('p')
    status.className = 'panel-help'
    status.textContent = STATUS_COPY[post.status](post)
    panel.appendChild(status)

    /*
     * Both fields below are site time, not the browser's: the server prints
     * them and reads them back in the app's own timezone, and a datetime-local
     * input has no way to say so on its own. An editor on a phone set three
     * hours east would otherwise read the site's 09:00 as their own and file
     * an article for the wrong morning, so the clock is named in the help.
     */
    const zone = context.bootstrap.timezone

    const when = textField({
        label: 'Publish date',
        value: post.publishedAt ?? '',
        type: 'datetime-local',
        help: `A date in the future schedules the article. Empty keeps it a draft. Site time (${zone}).`,
        onInput: (value) => context.setField('published_at', value === '' ? null : value),
    })
    panel.appendChild(when)

    /*
     * The dateline is the date printed on the article, and publishing takes it:
     * an article dated ahead is scheduled by the same click that puts a
     * today-dated one live. See Post::publishableAt().
     */
    panel.appendChild(
        textField({
            label: 'Dateline',
            value: post.datedAt ?? '',
            type: 'datetime-local',
            help: `The date readers see, and the date publishing files it for. Site time (${zone}).`,
            onInput: (value) => context.setField('dated_at', value === '' ? null : value),
        })
    )

    const input = when.querySelector('input')

    const actions = document.createElement('div')
    actions.className = 'panel-actions'

    /*
     * The bar's publish button, which used to sit beside this panel's own,
     * saying the same thing with none of the dates around it. This is the one
     * that survived - so it is the panel's primary control rather than the
     * first of a row of equals.
     */
    actions.appendChild(
        button(
            post.publishSchedules ? `Schedule for ${post.publishesAt}` : 'Publish now',
            () => {
                /*
                 * Now is an instant, not a reading of the clock the browser is
                 * set to. The field above is site time, so a phone three hours
                 * east that sent its own wall clock was asking to be published
                 * three hours from now - "Publish now" left the article marked
                 * Scheduled and the page dark. An ISO 8601 stamp carries its
                 * offset, so the server converts rather than assumes.
                 *
                 * The dateline is already site time and goes up as it stands:
                 * an article dated ahead is filed for the date on its byline.
                 */
                const at = post.publishSchedules ? post.datedAt : new Date().toISOString()

                /*
                 * Only the dateline is in the format this input speaks - an
                 * instant would silently empty it. Saving closes the panel and
                 * the next one is built from the server's answer regardless, so
                 * this is nothing more than not blanking the field on the way out.
                 */
                if (input && post.publishSchedules && at !== null) {
                    input.value = at
                }

                context.setField('published_at', at)
                context.save()
            },
            'primary'
        )
    )

    if (post.status !== 'draft') {
        actions.appendChild(
            button('Unpublish', () => {
                if (input) {
                    input.value = ''
                }

                context.setField('published_at', null)
                context.save()
            })
        )
    }

    panel.appendChild(actions)

    const pending = context.bootstrap.revision

    if (pending !== null) {
        const rewrite = document.createElement('div')
        rewrite.className = 'panel-field'

        const label = document.createElement('label')
        label.textContent = 'Pending version'
        rewrite.appendChild(label)

        const note = document.createElement('p')
        note.className = 'panel-help'
        note.textContent = pending.note
        rewrite.appendChild(note)

        const links = document.createElement('div')
        links.className = 'panel-actions'

        for (const [text, href] of [
            ['Preview it', pending.previewUrl],
            ['See changes', pending.diffUrl],
            ['Edit it', pending.editUrl],
        ]) {
            const link = document.createElement('a')
            link.className = 'panel-button'
            link.href = href
            link.textContent = text
            links.appendChild(link)
        }

        links.appendChild(button('Approve & replace', () => context.approveRevision(), 'primary'))
        rewrite.appendChild(links)
        panel.appendChild(rewrite)
    }

    const stats = document.createElement('p')
    stats.className = 'panel-help'
    stats.textContent = `${post.readingMinutes ?? 1} min read · ${post.viewsCount ?? 0} views`
    panel.appendChild(stats)

    const danger = document.createElement('div')
    danger.className = 'panel-actions panel-danger'
    danger.appendChild(
        button(
            'Delete article',
            () => {
                void openDialog({
                    title: 'Delete this article?',
                    fields: [
                        {
                            name: 'slug',
                            label: `Type the slug to confirm: ${post.slug}`,
                            placeholder: post.slug,
                        },
                    ],
                    confirmLabel: 'Delete',
                    destructive: true,
                }).then((answer) => {
                    if (answer !== null && answer.slug.trim() === post.slug) {
                        context.destroy()
                    }
                })
            },
            'destructive'
        )
    )
    panel.appendChild(danger)
}

const STATUS_COPY: Record<EditorPost['status'], (post: EditorPost) => string> = {
    draft: () => 'Not published. Only an admin can open this URL.',
    scheduled: (post) =>
        `Scheduled for ${post.publishedAtLabel}. Nobody else can open it until then.`,
    published: (post) => `Live since ${post.publishedAtLabel}.`,
}

/**
 * Seventeen things, which as a wrap of worded pills was a paragraph to read
 * every time somebody wanted a bullet list. Two columns of tiles, each with the
 * shape of the block on it: the eight prose ones are recognizable by their mark
 * alone, and the editorial blocks keep their names because "Barber brief" is
 * not a shape anybody could guess.
 */
function buildInsert(panel: HTMLElement, context: PanelContext): void {
    const note = document.createElement('p')
    note.className = 'panel-help'
    note.textContent = 'Added where the cursor is, or at the end of the article.'
    panel.appendChild(note)

    for (const [group, kinds] of INSERTS) {
        section(panel, group)

        const grid = document.createElement('div')
        grid.className = 'panel-inserts'

        for (const [kind, label, mark] of kinds) {
            const tile = document.createElement('button')
            tile.type = 'button'
            tile.className = 'panel-insert'
            tile.appendChild(icon(mark))
            tile.appendChild(document.createTextNode(label))
            tile.addEventListener('click', () => {
                context.insert(kind)
                closePanel()
            })

            grid.appendChild(tile)
        }

        panel.appendChild(grid)
    }
}

const INSERTS: Array<[string, Array<[InsertKind, string, IconName]>]> = [
    [
        'Prose',
        [
            ['paragraph', 'Paragraph', 'paragraph'],
            ['h2', 'Heading', 'heading'],
            ['h3', 'Subheading', 'subheading'],
            ['ul', 'Bullets', 'bullets'],
            ['ol', 'Numbers', 'numbers'],
            ['quote', 'Quote', 'quote'],
            ['code', 'Code', 'code'],
            ['divider', 'Divider', 'divider'],
        ],
    ],
    ['Pictures', [['image', 'Image', 'image']]],
    [
        'Editorial blocks',
        [
            ['style-entry', 'Project entry', 'anchor'],
            ['brief', 'Project brief', 'brief'],
            ['steps', 'Steps', 'steps'],
            ['gallery', 'Gallery', 'gallery'],
            ['try-on', 'Drawing callout', 'face'],
            ['youtube', 'YouTube', 'video'],
            ['markdown', 'Raw markdown', 'code'],
        ],
    ],
]

/* ---------------------------------------------------------------- builders */

interface TextFieldOptions {
    label: string
    value: string
    help?: string
    placeholder?: string
    multiline?: boolean
    type?: string
    counter?: number
    onInput: (value: string) => void
}

function textField(options: TextFieldOptions): HTMLElement {
    const wrapper = document.createElement('div')
    wrapper.className = 'panel-field'

    const label = document.createElement('label')
    label.textContent = options.label
    wrapper.appendChild(label)

    const input = options.multiline
        ? document.createElement('textarea')
        : document.createElement('input')

    if (input instanceof HTMLTextAreaElement) {
        input.rows = 3
    } else {
        input.type = options.type ?? 'text'
    }

    input.value = options.value
    input.placeholder = options.placeholder ?? ''
    wrapper.appendChild(input)

    const help = document.createElement('p')
    help.className = 'panel-help'

    const describe = (): void => {
        const limit =
            options.counter === undefined ? '' : ` ${input.value.length}/${options.counter}`
        help.textContent = `${options.help ?? ''}${limit}`.trim()
    }

    describe()

    if (help.textContent !== '') {
        wrapper.appendChild(help)
    }

    input.addEventListener('input', () => {
        options.onInput(input.value)
        describe()
    })

    return wrapper
}

function selectField(options: {
    label: string
    value: string
    options: Array<{ value: string; label: string }>
    onChange: (value: string) => void
}): HTMLElement {
    const wrapper = document.createElement('div')
    wrapper.className = 'panel-field'

    const label = document.createElement('label')
    label.textContent = options.label
    wrapper.appendChild(label)

    const select = document.createElement('select')

    for (const option of options.options) {
        const element = document.createElement('option')
        element.value = option.value
        element.textContent = option.label
        element.selected = option.value === options.value
        select.appendChild(element)
    }

    select.addEventListener('change', () => options.onChange(select.value))
    wrapper.appendChild(select)

    return wrapper
}

function checkboxes(options: {
    label: string
    options: Array<{ value: string; label: string }>
    selected: string[]
    help?: string
    onChange: (values: string[]) => void
}): HTMLElement {
    const wrapper = document.createElement('div')
    wrapper.className = 'panel-field'

    const label = document.createElement('label')
    label.textContent = options.label
    wrapper.appendChild(label)

    const list = document.createElement('div')
    list.className = 'panel-checkboxes'
    const chosen = new Set(options.selected)

    for (const option of options.options) {
        const line = document.createElement('label')
        line.className = 'panel-checkbox'

        const input = document.createElement('input')
        input.type = 'checkbox'
        input.value = option.value
        input.checked = chosen.has(option.value)

        input.addEventListener('change', () => {
            if (input.checked) {
                chosen.add(option.value)
            } else {
                chosen.delete(option.value)
            }

            options.onChange([...chosen])
        })

        line.appendChild(input)
        line.appendChild(document.createTextNode(option.label))
        list.appendChild(line)
    }

    wrapper.appendChild(list)

    if (options.help) {
        const help = document.createElement('p')
        help.className = 'panel-help'
        help.textContent = options.help
        wrapper.appendChild(help)
    }

    return wrapper
}

function button(
    label: string,
    onClick: () => void,
    tone?: 'primary' | 'destructive'
): HTMLButtonElement {
    const element = document.createElement('button')
    element.type = 'button'
    element.className = 'panel-button'
    element.textContent = label

    if (tone !== undefined) {
        element.dataset[tone] = 'true'
    }

    element.addEventListener('click', onClick)

    return element
}

/** A pending list wins over the saved one, so reopening a panel shows the edit. */
function pendingList(context: PanelContext, field: 'categories' | 'authors'): string[] | null {
    const pending = context.pending()[field]

    return Array.isArray(pending) ? pending : null
}
