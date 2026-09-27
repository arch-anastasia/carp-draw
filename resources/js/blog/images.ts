/**
 * Everything that happens to a picture on an article page.
 *
 * Right-click one and this is the menu: replace it with a file off your
 * machine, crop it, describe it, caption it, move it up or down the article,
 * change how the text wraps around it, or take it out. The cover works the same
 * way, minus the operations that only mean something inside prose.
 *
 * Nothing here writes to the server. Every operation edits the DOM and marks
 * the article dirty; the save that follows serialises the block back to
 * markdown and the server re-renders it - so what a writer sees after saving is
 * the published rendering, not the browser's approximation of it.
 */

import { blockGroupOf, blockGroups, type BlockGroup } from './htmlToMarkdown'
import { cropImage } from './imageCropper'
import { openDialog, promptDialog } from './dialog'
import { openMenu, type MenuItem } from './menu'
import type { UploadedImage } from './types'

/** What the editor lends this module. */
export interface ImageContext {
    body: HTMLElement
    /** Uploads bytes and resolves with the stored picture, or null on failure. */
    upload: (file: Blob, alt: string) => Promise<UploadedImage | null>
    markBodyDirty: () => void
    setCover: (image: UploadedImage | null) => void
    startEditing: () => void
    notify: (message: string, tone?: 'error') => void
}

/**
 * How the renderer draws each layout hint. Mirrors the `match` in
 * App\Services\BlogContent::responsiveImages(), so a picture realigned here
 * looks the way it will look once the markdown has been through the server.
 */
const ALIGNMENTS: Array<{ hint: string; label: string; classes: string }> = [
    { hint: 'default', label: 'Full width', classes: 'block mx-auto my-8 w-full rounded-box' },
    { hint: 'small', label: 'Small, centered', classes: 'block mx-auto my-6 max-w-xs rounded-box' },
    {
        hint: 'left',
        label: 'Float left',
        classes: 'float-left mr-6 mb-4 blog-inline-image rounded-box',
    },
    {
        hint: 'right',
        label: 'Float right',
        classes: 'float-right ml-6 mb-4 blog-inline-image rounded-box',
    },
]

const BORDER_CLASSES = ['border', 'border-base-300']

export function attachImageMenus(context: ImageContext): void {
    context.body.addEventListener('contextmenu', (event) => {
        const image = imageAt(event.target)

        if (image === null) {
            return
        }

        event.preventDefault()
        /*
         * Right-clicking a picture is an edit, so it turns editing on rather
         * than asking the writer to find the toggle first.
         */
        context.startEditing()
        select(context.body, image)
        openMenu(event.clientX, event.clientY, bodyMenu(context, image))
    })

    const cover = document.querySelector<HTMLImageElement>('[data-blog-editor-cover]')

    cover?.addEventListener('contextmenu', (event) => {
        event.preventDefault()
        context.startEditing()
        openMenu(event.clientX, event.clientY, coverMenu(context, cover))
    })

    const empty = document.querySelector<HTMLButtonElement>('[data-blog-editor-cover-empty]')

    empty?.addEventListener('click', () => {
        void chooseFile().then(async (file) => {
            if (file === null) {
                return
            }

            const uploaded = await context.upload(file, '')

            if (uploaded !== null) {
                context.setCover(uploaded)
            }
        })
    })
}

/** Which block of the body a picture sits in, by identity of its nodes. */
function positionOf(groups: BlockGroup[], image: Element): number {
    return groups.findIndex((group) =>
        group.nodes.some((node) => node === image || node.contains(image))
    )
}

/** The picture under the pointer, if the pointer was over one. */
function imageAt(target: EventTarget | null): HTMLImageElement | null {
    if (!(target instanceof Element)) {
        return null
    }

    const image = target.tagName === 'IMG'
        ? (target as HTMLImageElement)
        : target.querySelector<HTMLImageElement>('img')

    return image?.closest('.blog-article-cta') ? null : image
}

function select(body: HTMLElement, image: HTMLImageElement): void {
    for (const marked of Array.from(body.querySelectorAll('.blog-image-selected'))) {
        marked.classList.remove('blog-image-selected')
    }

    image.classList.add('blog-image-selected')
}

function bodyMenu(context: ImageContext, image: HTMLImageElement): MenuItem[] {
    const groups = blockGroups(context.body)
    const position = positionOf(groups, image)

    return [
        { label: 'Picture', heading: true },
        { label: 'Replace image…', onSelect: () => void replace(context, image) },
        { label: 'Crop image…', onSelect: () => void crop(context, image) },
        { label: 'Alt text…', onSelect: () => void describe(context, image) },
        { label: 'Caption…', onSelect: () => void caption(context, image) },

        { label: 'Layout', heading: true, separator: true },
        ...ALIGNMENTS.map(
            (alignment): MenuItem => ({
                label: alignment.label,
                checked: hintOf(image) === alignment.hint,
                onSelect: () => align(context, image, alignment.hint),
            })
        ),

        { label: 'Order', heading: true, separator: true },
        {
            label: 'Move up',
            disabled: position <= 0,
            onSelect: () => move(context, image, -1),
        },
        {
            label: 'Move down',
            disabled: position < 0 || position >= groups.length - 1,
            onSelect: () => move(context, image, 1),
        },

        {
            label: 'Remove image',
            separator: true,
            destructive: true,
            onSelect: () => remove(context, image),
        },
    ]
}

function coverMenu(context: ImageContext, cover: HTMLImageElement): MenuItem[] {
    return [
        { label: 'Cover image', heading: true },
        {
            label: 'Replace cover…',
            onSelect: () => {
                void chooseFile().then(async (file) => {
                    if (file === null) {
                        return
                    }

                    const uploaded = await context.upload(file, cover.alt)

                    if (uploaded !== null) {
                        context.setCover(uploaded)
                    }
                })
            },
        },
        {
            label: 'Crop cover…',
            onSelect: () => {
                void cropImage(cover.currentSrc || cover.src, 'cover').then(async (blob) => {
                    if (blob === null) {
                        context.notify('That picture could not be cropped here.', 'error')

                        return
                    }

                    const uploaded = await context.upload(blob, cover.alt)

                    if (uploaded !== null) {
                        context.setCover(uploaded)
                    }
                })
            },
        },
        {
            label: 'Alt text…',
            onSelect: () => {
                void promptDialog('Describe the cover', {
                    name: 'alt',
                    label: 'Alt text',
                    value: cover.alt,
                    help: 'What the picture shows, for a reader who cannot see it.',
                }).then((alt) => {
                    if (alt === null) {
                        return
                    }

                    cover.alt = alt
                    context.setCover({
                        id: '',
                        url: cover.getAttribute('src') ?? '',
                        alt,
                        markdown: '',
                        mime: null,
                        width: null,
                        height: null,
                        bytes: null,
                    })
                })
            },
        },
        {
            label: 'Remove cover',
            separator: true,
            destructive: true,
            onSelect: () => context.setCover(null),
        },
    ]
}

async function replace(context: ImageContext, image: HTMLImageElement): Promise<void> {
    const file = await chooseFile()

    if (file === null) {
        return
    }

    const uploaded = await context.upload(file, image.alt)

    if (uploaded !== null) {
        swap(context, image, uploaded)
    }
}

async function crop(context: ImageContext, image: HTMLImageElement): Promise<void> {
    const blob = await cropImage(image.currentSrc || image.src, nameOf(image))

    if (blob === null) {
        context.notify('That picture could not be cropped here.', 'error')

        return
    }

    const uploaded = await context.upload(blob, image.alt)

    if (uploaded !== null) {
        swap(context, image, uploaded)
    }
}

async function describe(context: ImageContext, image: HTMLImageElement): Promise<void> {
    const alt = await promptDialog('Describe the picture', {
        name: 'alt',
        label: 'Alt text',
        value: image.alt,
        help: 'What the picture shows, for a reader who cannot see it.',
    })

    if (alt === null) {
        return
    }

    image.alt = alt
    context.markBodyDirty()
}

/**
 * A markdown title - `![alt](url "Front view")` - which the renderer prints
 * under the picture. Clearing it removes the <figure> along with the caption,
 * because an empty caption bar under a photograph is worse than none.
 */
async function caption(context: ImageContext, image: HTMLImageElement): Promise<void> {
    const figure = image.closest('figure')
    const existing = figure?.querySelector('figcaption')?.textContent?.trim() ?? ''

    const answer = await openDialog({
        title: 'Caption',
        fields: [
            {
                name: 'caption',
                label: 'Printed under the picture',
                value: existing,
                help: 'Leave empty for no caption.',
            },
        ],
    })

    if (answer === null) {
        return
    }

    const text = (answer.caption ?? '').trim()
    const picture = image.closest('picture') ?? image

    if (text === '') {
        if (figure) {
            figure.replaceWith(picture)
        }

        context.markBodyDirty()

        return
    }

    if (figure) {
        const existingCaption = figure.querySelector('figcaption')

        if (existingCaption) {
            existingCaption.textContent = text
        } else {
            const created = document.createElement('figcaption')
            created.textContent = text
            figure.appendChild(created)
        }

        context.markBodyDirty()

        return
    }

    const wrapper = document.createElement('figure')
    carryBlockMarkers(picture, wrapper)
    picture.replaceWith(wrapper)
    wrapper.appendChild(picture)

    const created = document.createElement('figcaption')
    created.textContent = text
    wrapper.appendChild(created)

    context.markBodyDirty()
}

function align(context: ImageContext, image: HTMLImageElement, hint: string): void {
    const alignment = ALIGNMENTS.find((candidate) => candidate.hint === hint)

    if (alignment === undefined) {
        return
    }

    image.dataset.mdHint = hint
    image.className = [...alignment.classes.split(' '), ...BORDER_CLASSES].join(' ')
    context.markBodyDirty()
}

function move(context: ImageContext, image: HTMLImageElement, direction: -1 | 1): void {
    /*
     * One walk of the body, not two. blockGroups() builds fresh objects every
     * call, so looking a group up separately and then asking the array for its
     * index always answered -1 - which silently disabled both of these.
     */
    const groups = blockGroups(context.body)
    const position = positionOf(groups, image)
    const group = groups[position]
    const neighbour = groups[position + direction]

    if (group === undefined || neighbour === undefined) {
        return
    }

    if (direction === -1) {
        const anchor = neighbour.nodes[0]

        for (const node of group.nodes) {
            context.body.insertBefore(node, anchor)
        }
    } else {
        let previous = neighbour.nodes[neighbour.nodes.length - 1] as ChildNode

        for (const node of group.nodes) {
            previous.after(node)
            previous = node as ChildNode
        }
    }

    context.markBodyDirty()
    image.scrollIntoView?.({ block: 'center', behavior: 'smooth' })
}

function remove(context: ImageContext, image: HTMLImageElement): void {
    const group = blockGroupOf(context.body, image)

    if (group === null) {
        return
    }

    /*
     * A picture standing alone is its own block and goes with it. One sitting
     * inside a paragraph is not - taking the paragraph out with it would delete
     * prose nobody asked to delete - so only the picture goes.
     */
    const standalone = group.nodes.every(
        (node) => node.textContent === null || node.textContent.trim() === ''
    )

    if (standalone) {
        for (const node of group.nodes) {
            node.parentNode?.removeChild(node)
        }
    } else {
        ;(image.closest('figure') ?? image.closest('picture') ?? image).remove()
    }

    context.markBodyDirty()
}

/**
 * Point an existing picture at new bytes.
 *
 * The `<source>` elements go: they are the renderer's responsive variants of
 * the file that was there, and they would otherwise keep being served in place
 * of the one that just replaced it. The next render rebuilds them.
 */
function swap(context: ImageContext, image: HTMLImageElement, uploaded: UploadedImage): void {
    const picture = image.closest('picture')

    for (const source of Array.from(picture?.querySelectorAll('source') ?? [])) {
        source.remove()
    }

    image.removeAttribute('srcset')
    image.removeAttribute('sizes')
    image.src = uploaded.url

    if (uploaded.width !== null && uploaded.height !== null) {
        image.width = uploaded.width
        image.height = uploaded.height
    }

    if (uploaded.alt !== null && uploaded.alt !== '') {
        image.alt = uploaded.alt
    }

    context.markBodyDirty()
}

/**
 * Keep a block's markers on the element that replaces it, or the block loses
 * the markdown it came from and the one after it swallows what is left.
 */
function carryBlockMarkers(from: Element, to: Element): void {
    for (const attribute of ['data-md-block', 'data-md-source', 'data-md-atomic']) {
        const value = from.getAttribute(attribute)

        if (value !== null) {
            to.setAttribute(attribute, value)
            from.removeAttribute(attribute)
        }
    }
}

function hintOf(image: HTMLImageElement): string {
    const declared = image.dataset.mdHint

    if (declared !== undefined) {
        return declared
    }

    if (image.classList.contains('float-left')) {
        return 'left'
    }

    if (image.classList.contains('float-right')) {
        return 'right'
    }

    if (image.classList.contains('max-w-xs')) {
        return 'small'
    }

    return 'default'
}

function nameOf(image: HTMLImageElement): string {
    const source = image.getAttribute('src') ?? 'image'

    return (source.split('/').pop() ?? 'image').replace(/\.[a-z0-9]+$/i, '') || 'image'
}

/** The file picker, as a promise. Resolves null when it is dismissed. */
export function chooseFile(): Promise<File | null> {
    return new Promise((resolve) => {
        const input = document.createElement('input')
        input.type = 'file'
        input.accept = 'image/*'
        input.style.display = 'none'

        input.addEventListener(
            'change',
            () => {
                const file = input.files?.[0] ?? null
                input.remove()
                resolve(file)
            },
            { once: true }
        )

        /*
         * A dismissed picker fires no change event in most browsers. The
         * promise is left pending rather than resolved on a timer: nothing
         * downstream waits on it, and guessing at a duration would cancel a
         * writer who was still choosing.
         */
        document.body.appendChild(input)
        input.click()
    })
}
