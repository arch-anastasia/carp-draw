/**
 * The rendered article, written back as the markdown it is stored as.
 *
 * An article's body is GitHub-flavored markdown; the page a writer edits is the
 * HTML that markdown renders to. Converting the whole page back on every save
 * would mean re-authoring the entire article through the browser's idea of
 * HTML - and a fenced code sample, a hand-written table or an editorial
 * directive does not survive that trip.
 *
 * So the server hands over the article a block at a time, each top-level
 * element carrying the markdown it came from in `data-md-source` (see
 * App\Services\BlogContent::renderEditable). A block whose HTML is byte for
 * byte what was rendered is written back from its source, untouched. Only the
 * blocks somebody actually typed into are converted, and the conversion only
 * ever has to handle one paragraph's worth of HTML at a time.
 *
 * Blocks marked `data-md-atomic` are never converted at all, whatever happened
 * to their HTML: they are the directives, and their source is the only
 * description of them that exists.
 */

/** What a block looked like when the page arrived. */
export interface BlockSnapshot {
    source: string
    html: string
    atomic: boolean
}

/** One top-level unit of the body: the nodes of a single markdown block. */
export interface BlockGroup {
    id: string | null
    nodes: Node[]
}

/** Elements that start a line of their own in markdown. */
const BLOCK_TAGS = new Set([
    'ADDRESS',
    'ARTICLE',
    'ASIDE',
    'BLOCKQUOTE',
    'DIV',
    'DL',
    'FIGURE',
    'FOOTER',
    'H1',
    'H2',
    'H3',
    'H4',
    'H5',
    'H6',
    'HEADER',
    'HR',
    'LI',
    'OL',
    'P',
    'PRE',
    'SECTION',
    'TABLE',
    'UL',
])

/**
 * The layout hint an image carries, read back off the classes the renderer
 * gave it. `![alt](url#left)` floats a picture and wraps the text around it,
 * and losing the hash on save would silently re-flow the article.
 */
const HINT_CLASSES: Array<[string, string]> = [
    ['float-left', 'left'],
    ['float-right', 'right'],
    ['max-w-xs', 'small'],
]

/**
 * Where a block of markdown starts and stops in the live DOM.
 *
 * A block is an element carrying `data-md-block` plus everything after it up to
 * the next one - which is how a single markdown paragraph that renders as two
 * elements stays one unit, and how a paragraph typed at the end of a block
 * becomes part of the block it was typed into.
 */
export function blockGroups(container: HTMLElement): BlockGroup[] {
    return groupsOf(container)
}

/** The block a node sits inside, or null for a node outside the body. */
export function blockGroupOf(container: HTMLElement, node: Node): BlockGroup | null {
    return (
        groupsOf(container).find((group) =>
            group.nodes.some((candidate) => candidate === node || candidate.contains(node))
        ) ?? null
    )
}

function groupsOf(container: HTMLElement): BlockGroup[] {
    const groups: BlockGroup[] = []
    let current: BlockGroup | null = null

    for (const node of Array.from(container.childNodes)) {
        if (node.nodeType === Node.TEXT_NODE && (node.textContent ?? '').trim() === '') {
            continue
        }

        const id =
            node.nodeType === Node.ELEMENT_NODE
                ? (node as Element).getAttribute('data-md-block')
                : null

        if (id !== null || current === null) {
            current = { id, nodes: [] }
            groups.push(current)
        }

        current.nodes.push(node)
    }

    return groups
}

function nodesHtml(nodes: Node[]): string {
    return nodes
        .map((node) =>
            node.nodeType === Node.ELEMENT_NODE
                ? (node as Element).outerHTML
                : (node.textContent ?? '')
        )
        .join('')
}

/** UTF-8 safe: an article is full of curly quotes, and atob() is bytes. */
function decodeSource(value: string): string {
    const binary = atob(value)
    const bytes = Uint8Array.from(binary, (character) => character.charCodeAt(0))

    return new TextDecoder().decode(bytes)
}

/**
 * What every block looked like before anybody typed. Taken once, when the
 * editor attaches; comparing against it at save time is what tells an edited
 * block from an untouched one.
 */
export function readBlocks(container: HTMLElement): Map<string, BlockSnapshot> {
    const snapshots = new Map<string, BlockSnapshot>()

    for (const group of groupsOf(container)) {
        if (group.id === null) {
            continue
        }

        const first = group.nodes[0] as Element
        const source = first.getAttribute('data-md-source')

        if (source === null) {
            continue
        }

        snapshots.set(group.id, {
            source: decodeSource(source),
            html: nodesHtml(group.nodes),
            atomic: first.hasAttribute('data-md-atomic'),
        })
    }

    return snapshots
}

/**
 * The whole body as markdown: sources for the blocks nobody touched, a
 * conversion for the ones somebody did.
 */
export function serializeBody(
    container: HTMLElement,
    snapshots: Map<string, BlockSnapshot>
): string {
    return groupsOf(container)
        .map((group) => {
            const snapshot = group.id === null ? undefined : snapshots.get(group.id)

            if (snapshot && (snapshot.atomic || snapshot.html === nodesHtml(group.nodes))) {
                return snapshot.source
            }

            return group.nodes.map((node) => nodeToMarkdown(node, 0)).join('\n\n')
        })
        .map((block) => block.trim())
        .filter((block) => block !== '')
        .join('\n\n')
}

function nodeToMarkdown(node: Node, depth: number): string {
    if (node.nodeType === Node.TEXT_NODE) {
        return escapeText(node.textContent ?? '').trim()
    }

    if (node.nodeType !== Node.ELEMENT_NODE) {
        return ''
    }

    return elementToMarkdown(node as Element, depth)
}

/** One block-level element, as the markdown that renders it. */
export function elementToMarkdown(element: Element, depth = 0): string {
    const tag = element.tagName

    if (/^H[1-6]$/.test(tag)) {
        return headingToMarkdown(element, Number(tag.slice(1)))
    }

    switch (tag) {
        case 'HR':
            return '---'

        case 'PRE':
            return preToMarkdown(element)

        case 'UL':
        case 'OL':
            return listToMarkdown(element, depth)

        case 'BLOCKQUOTE':
            return childrenToMarkdown(element, depth)
                .split('\n')
                .map((line) => (line === '' ? '>' : `> ${line}`))
                .join('\n')

        case 'TABLE':
            return tableToMarkdown(element)

        case 'FIGURE':
            return figureToMarkdown(element)

        case 'PICTURE':
        case 'IMG':
            return imageToMarkdown(element, '')

        case 'BR':
            return ''

        default:
            return childrenToMarkdown(element, depth)
    }
}

/**
 * A container's children, with runs of inline content gathered into paragraphs
 * and block elements kept apart. The same walk serves a `<p>`, a `<li>`, a
 * `<blockquote>` and a stray `<div>` the browser left behind.
 */
function childrenToMarkdown(element: Element, depth: number): string {
    const blocks: string[] = []
    let inline = ''

    const flush = (): void => {
        /*
         * Runs of spaces collapse - two of them are what an emptied <strong>
         * leaves behind - except at the end of a line, where exactly two are a
         * hard break that <br> just wrote.
         */
        const text = inline.replace(/ {2,}(?!\n)/g, ' ').trim()

        if (text !== '') {
            blocks.push(text)
        }

        inline = ''
    }

    for (const child of Array.from(element.childNodes)) {
        if (child.nodeType === Node.ELEMENT_NODE && BLOCK_TAGS.has((child as Element).tagName)) {
            flush()
            const block = elementToMarkdown(child as Element, depth)

            if (block.trim() !== '') {
                blocks.push(block)
            }

            continue
        }

        inline += inlineToMarkdown(child)
    }

    flush()

    return blocks.join('\n\n')
}

function headingToMarkdown(element: Element, level: number): string {
    const hashes = '#'.repeat(level)

    /*
     * A style entry - "## The taper {.style-entry}" - renders as a heading
     * wrapping a span that owns the anchor, because the "jump to a hairstyle"
     * list and the ItemList schema both link to it. Written back with its
     * directive, or the entry quietly stops being one.
     */
    const anchor = element.firstElementChild

    if (
        element.children.length === 1 &&
        anchor?.tagName === 'SPAN' &&
        anchor.id !== '' &&
        anchor.classList.contains('scroll-mt-24')
    ) {
        return `${hashes} ${collapse(anchor.textContent ?? '')} {.style-entry #${anchor.id}}`
    }

    /*
     * Every other id was handed out by the renderer from the heading's own
     * words, so it is not written back: it is derived again on the next render,
     * and carrying it here would freeze an anchor to a heading's old wording.
     */
    return `${hashes} ${collapse(inlineChildren(element))}`
}

function preToMarkdown(element: Element): string {
    const code = element.querySelector('code')
    const language = /language-([\w-]+)/.exec(code?.className ?? '')?.[1] ?? ''
    const text = (code ?? element).textContent ?? ''

    return `\`\`\`${language}\n${text.replace(/\n$/, '')}\n\`\`\``
}

function listToMarkdown(list: Element, depth: number): string {
    const ordered = list.tagName === 'OL'
    const start = Number(list.getAttribute('start') ?? '1') || 1

    return Array.from(list.children)
        .filter((child) => child.tagName === 'LI')
        .map((item, index) => {
            const marker = ordered ? `${start + index}. ` : '- '
            const indent = ' '.repeat(marker.length)
            const body = childrenToMarkdown(item, depth + 1)

            return body
                .split('\n')
                .map((line, position) =>
                    position === 0 ? marker + line : line === '' ? '' : indent + line
                )
                .join('\n')
        })
        .join('\n')
}

function tableToMarkdown(table: Element): string {
    const rows = Array.from(table.querySelectorAll('tr'))

    if (rows.length === 0) {
        return ''
    }

    const cellsOf = (row: Element): string[] =>
        Array.from(row.children).map((cell) => collapse(inlineChildren(cell)).replace(/\|/g, '\\|'))

    const header = cellsOf(rows[0])
    const divider = header.map(() => '---')
    const body = rows.slice(1).map(cellsOf)

    return [header, divider, ...body].map((cells) => `| ${cells.join(' | ')} |`).join('\n')
}

function figureToMarkdown(figure: Element): string {
    const caption = collapse(figure.querySelector('figcaption')?.textContent ?? '')
    const image = figure.querySelector('picture, img')

    return image === null ? childrenToMarkdown(figure, 0) : imageToMarkdown(image, caption)
}

/**
 * A picture, back as `![alt](url#hint "caption")`.
 *
 * The `<source>` elements a `<picture>` carries are the renderer's responsive
 * variants, derived from the file on disk - they are not in the markdown and
 * are not written back into it.
 */
function imageToMarkdown(element: Element, caption: string): string {
    const image = (
        element.tagName === 'IMG' ? element : element.querySelector('img')
    ) as HTMLImageElement | null

    if (image === null) {
        return ''
    }

    const source = image.getAttribute('src') ?? ''

    if (source === '') {
        return ''
    }

    const alt = (image.getAttribute('alt') ?? '').replace(/[[\]]/g, '')
    const hint = hintOf(image)
    const url = hint === '' ? source : `${source}#${hint}`
    const title = caption === '' ? '' : ` "${caption.replace(/"/g, "'")}"`
    /* The marker is not visible in the rendered picture, so without this an
       edit three words away from a tryable image would quietly un-flag it. */
    const tryable = image.hasAttribute('data-gallery-tryable') ? '{.tryable}' : ''

    return `![${alt}](${url}${title})${tryable}`
}

/** An explicit hint wins: it is what the editor sets when alignment changes. */
function hintOf(image: Element): string {
    const declared = image.getAttribute('data-md-hint')

    if (declared !== null) {
        return declared === 'default' ? '' : declared
    }

    for (const element of [image, image.closest('figure')]) {
        for (const [className, hint] of HINT_CLASSES) {
            if (element?.classList.contains(className)) {
                return hint
            }
        }
    }

    return ''
}

function inlineChildren(element: Element): string {
    return Array.from(element.childNodes).map(inlineToMarkdown).join('')
}

function inlineToMarkdown(node: Node): string {
    if (node.nodeType === Node.TEXT_NODE) {
        return escapeText(node.textContent ?? '')
    }

    if (node.nodeType !== Node.ELEMENT_NODE) {
        return ''
    }

    const element = node as Element

    switch (element.tagName) {
        case 'BR':
            return '  \n'

        case 'IMG':
        case 'PICTURE':
            return imageToMarkdown(element, '')

        case 'CODE':
            return `\`${(element.textContent ?? '').replace(/\n/g, ' ')}\``

        case 'STRONG':
        case 'B':
            return wrap(inlineChildren(element), '**')

        case 'EM':
        case 'I':
            return wrap(inlineChildren(element), '*')

        case 'DEL':
        case 'S':
        case 'STRIKE':
            return wrap(inlineChildren(element), '~~')

        case 'A': {
            const href = element.getAttribute('href') ?? ''
            const text = inlineChildren(element)

            return href === '' ? text : `[${text}](${href})`
        }

        default:
            return inlineChildren(element)
    }
}

/**
 * Emphasis around nothing but spaces is not emphasis - CommonMark leaves the
 * asterisks on the page. Keeps `<strong> </strong>`, which a browser produces
 * readily while somebody is selecting text, from printing literal stars.
 */
function wrap(text: string, marker: string): string {
    if (text.trim() === '') {
        return text
    }

    const [, before, middle, after] = /^(\s*)([\s\S]*?)(\s*)$/.exec(text) as RegExpExecArray

    return `${before}${marker}${middle}${marker}${after}`
}

function collapse(text: string): string {
    return text.replace(/\s+/g, ' ').trim()
}

/**
 * Text as markdown source. HTML's newlines and runs of spaces are the
 * renderer's formatting rather than the article's, so they collapse; the
 * characters that would otherwise be read as markup are escaped.
 */
function escapeText(text: string): string {
    return text
        .replace(/\s+/g, ' ')
        .replace(/([\\`*[\]])/g, '\\$1')
        .replace(/^(\s*)([-+>#])/gm, '$1\\$2')
        .replace(/^(\s*\d+)([.)])\s/gm, '$1\\$2 ')
}
