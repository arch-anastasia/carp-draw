import { describe, it, expect } from 'vitest'
import { readBlocks, serializeBody, blockGroupOf } from '@/blog/htmlToMarkdown'

/**
 * The article, written back as markdown.
 *
 * The promise being tested is the one the whole in-page editor rests on: a
 * block nobody touched comes back byte for byte as it was authored, and only
 * the blocks somebody typed into are converted from HTML. A directive is never
 * converted at all, whatever the browser did to its markup.
 *
 * The fixtures carry the markers App\Services\BlogContent::renderEditable()
 * prints, because those markers are the contract.
 */

function encode(source: string): string {
    const bytes = new TextEncoder().encode(source)

    return btoa(String.fromCharCode(...bytes))
}

function body(html: string): HTMLElement {
    const container = document.createElement('div')
    container.innerHTML = html.trim()

    return container
}

/** One block, marked the way the server marks it. */
function block(source: string, html: string, index = 0, atomic = false): string {
    const markers =
        ` data-md-block="${index}" data-md-source="${encode(source)}"` +
        (atomic ? ' data-md-atomic="1" contenteditable="false"' : '')

    return html.replace(/^<([a-z0-9]+)/i, `<$1${markers}`)
}

function roundTrip(container: HTMLElement): string {
    return serializeBody(container, readBlocks(container))
}

describe('an untouched article', () => {
    it('comes back as the markdown it was authored as', () => {
        const container = body(
            block('## The taper', '<h2 id="the-taper">The taper</h2>', 0) +
                block(
                    'A gradient, **not** a length.',
                    '<p>A gradient, <strong>not</strong> a length.</p>',
                    1
                )
        )

        expect(roundTrip(container)).toBe('## The taper\n\nA gradient, **not** a length.')
    })

    it('keeps a fenced code sample exactly as it was written', () => {
        /*
         * The point of source-first serialisation: this markdown has blank
         * lines and indentation no HTML-to-markdown converter would preserve.
         */
        const source = '```php\nif ($fade) {\n\n    return true;\n}\n```'
        const container = body(
            block(
                source,
                '<pre><code class="language-php">if ($fade) {\n\n    return true;\n}\n</code></pre>'
            )
        )

        expect(roundTrip(container)).toBe(source)
    })

    it('keeps a block in the place the editor moved it to', () => {
        const container = body(
            block('First.', '<p>First.</p>', 0) + block('Second.', '<p>Second.</p>', 1)
        )

        container.insertBefore(container.children[1], container.children[0])

        expect(roundTrip(container)).toBe('Second.\n\nFirst.')
    })

    it('drops a block the editor removed', () => {
        const container = body(
            block('First.', '<p>First.</p>', 0) + block('Second.', '<p>Second.</p>', 1)
        )

        container.children[0].remove()

        expect(roundTrip(container)).toBe('Second.')
    })
})

describe('a directive', () => {
    it.each([
        [
            ':::upload:::',
            '<div class="blog-upload"><section><h2>Yours could be here.</h2><a href="/onboarding" data-track-cta="blog_upload">Try a haircut free</a></section></div>',
        ],
        [
            ':::gallery\n![A taper](/taper.webp){.tryable}\n:::',
            '<div class="blog-gallery"><figure class="blog-gallery-item"><span class="blog-gallery-frame"><img src="/taper.webp" data-gallery-tryable="/onboarding"><a class="blog-gallery-try blog-try-button blog-try-bottom-right" href="/onboarding">Try this on me</a></span></figure></div>',
        ],
    ])('preserves the source of %s with the new CTA markup', (source, html) => {
        const container = body(block(source, html, 0, true))
        const snapshots = readBlocks(container)

        container.querySelector('a')!.textContent = 'Changed in the browser'

        expect(serializeBody(container, snapshots)).toBe(source)
    })

    it('is written back from its source even when its HTML changed', () => {
        const source = ':::brief "Say this to your barber"\nA number two on the sides.\n:::'
        const container = body(
            block(
                source,
                '<div class="blog-brief"><p class="eyebrow">Say this to your barber</p><p class="brief-text">A number two on the sides.</p></div>',
                0,
                true
            )
        )

        /* Whatever the browser does to a widget, the markdown is the truth. */
        container.querySelector('p')?.remove()

        expect(roundTrip(container)).toBe(source)
    })
})

describe('a designed try-on directive', () => {
    it('stays atomic when nearby prose is edited', () => {
        const source = ':::try-on\nSee this <strong>cut</strong> on your face.\n:::'
        const container = body(
            block(
                source,
                `<aside class="blog-try-on blog-article-cta">
                    <div class="blog-cta-demo"><img src="/images/demo/noah-before.webp" alt="Before"><span>After</span></div>
                    <p>Try it before the cut</p><p>You can't un-cut it.<span>So look first.</span></p>
                    <p>See this <strong>cut</strong> on your face.</p>
                    <a class="blog-cta-button" href="/onboarding">Try this hairstyle free</a>
                    <div><svg aria-hidden="true"></svg>One selfie / 120+ looks / Photo stays yours</div>
                </aside>`,
                0,
                true
            ) + block('After the offer.', '<p>After the offer.</p>', 1)
        )
        const snapshots = readBlocks(container)
        container.querySelector('img')?.remove()
        container.lastElementChild!.textContent = 'Updated prose.'

        expect(serializeBody(container, snapshots)).toBe(`${source}\n\nUpdated prose.`)
    })
})

describe('an edited block', () => {
    it('is converted back to markdown', () => {
        const container = body(block('The old sentence.', '<p>The old sentence.</p>'))
        const snapshots = readBlocks(container)

        container.querySelector('p')!.innerHTML =
            'A <strong>new</strong> sentence with a <a href="/blog/fades">link</a>.'

        expect(serializeBody(container, snapshots)).toBe(
            'A **new** sentence with a [link](/blog/fades).'
        )
    })

    it('keeps a heading a heading, without the id the renderer gave it', () => {
        const container = body(block('## The taper', '<h2 id="the-taper">The taper</h2>'))
        const snapshots = readBlocks(container)

        container.querySelector('h2')!.textContent = 'The fade'

        expect(serializeBody(container, snapshots)).toBe('## The fade')
    })

    it('keeps a style entry pointing at its own anchor', () => {
        /*
         * A style entry owns the anchor the "jump to a hairstyle" list and the
         * ItemList schema both link to, so the directive has to survive.
         */
        const container = body(
            block(
                '## The taper {.style-entry}',
                '<h2><span id="the-taper" class="scroll-mt-24">The taper</span></h2>'
            )
        )
        const snapshots = readBlocks(container)

        container.querySelector('span')!.textContent = 'The low taper'

        expect(serializeBody(container, snapshots)).toBe(
            '## The low taper {.style-entry #the-taper}'
        )
    })

    it('writes a list back as a list', () => {
        const container = body(block('- One\n- Two', '<ul><li>One</li><li>Two</li></ul>'))
        const snapshots = readBlocks(container)

        const item = document.createElement('li')
        item.textContent = 'Three'
        container.querySelector('ul')!.appendChild(item)

        expect(serializeBody(container, snapshots)).toBe('- One\n- Two\n- Three')
    })

    it('writes a quote back as a quote', () => {
        const container = body(block('> Said once.', '<blockquote><p>Said once.</p></blockquote>'))
        const snapshots = readBlocks(container)

        container.querySelector('p')!.textContent = 'Said twice.'

        expect(serializeBody(container, snapshots)).toBe('> Said twice.')
    })

    it('writes a table back as a table', () => {
        const container = body(
            block(
                '| Length | Weeks |\n| --- | --- |\n| Two | Four |',
                '<table><thead><tr><th>Length</th><th>Weeks</th></tr></thead><tbody><tr><td>Two</td><td>Four</td></tr></tbody></table>'
            )
        )
        const snapshots = readBlocks(container)

        container.querySelector('td')!.textContent = 'Three'

        expect(serializeBody(container, snapshots)).toBe(
            '| Length | Weeks |\n| --- | --- |\n| Three | Four |'
        )
    })
})

describe('a picture', () => {
    const PICTURE =
        '<picture><source type="image/webp" srcset="/storage/blog/taper-480.webp 480w">' +
        '<img src="/storage/blog/taper.webp" alt="A taper" class="block mx-auto my-8 w-full rounded-box border border-base-300"></picture>'

    it("keeps its alt text and drops the renderer's responsive variants", () => {
        const container = body(block('![A taper](/storage/blog/taper.webp)', PICTURE))
        const snapshots = readBlocks(container)

        container.querySelector('img')!.alt = 'A low taper'

        expect(serializeBody(container, snapshots)).toBe('![A low taper](/storage/blog/taper.webp)')
    })

    it('keeps the layout hint the alignment menu set', () => {
        const container = body(block('![A taper](/storage/blog/taper.webp)', PICTURE))
        const snapshots = readBlocks(container)

        const image = container.querySelector('img')!
        image.dataset.mdHint = 'left'
        image.className = 'float-left mr-6 mb-4 max-w-[45%] rounded-box'

        expect(serializeBody(container, snapshots)).toBe(
            '![A taper](/storage/blog/taper.webp#left)'
        )
    })

    it('reads the hint back off the classes when nothing set one', () => {
        const container = body(
            block(
                '![A taper](/storage/blog/taper.webp#right)',
                PICTURE.replace('block mx-auto my-8 w-full', 'float-right ml-6 mb-4 max-w-[45%]')
            )
        )
        const snapshots = readBlocks(container)

        container.querySelector('img')!.alt = 'Changed'

        expect(serializeBody(container, snapshots)).toBe(
            '![Changed](/storage/blog/taper.webp#right)'
        )
    })

    it('keeps the tryable marker through an edit that did not touch it', () => {
        const container = body(
            block(
                '![A taper](/storage/blog/taper.webp){.tryable}',
                PICTURE.replace('alt="A taper"', 'alt="A taper" data-gallery-tryable="/onboarding"')
            )
        )
        const snapshots = readBlocks(container)

        container.querySelector('img')!.alt = 'A low taper'

        expect(serializeBody(container, snapshots)).toBe(
            '![A low taper](/storage/blog/taper.webp){.tryable}'
        )
    })

    it.each([
        ['', ''],
        ['float-left', '#left'],
        ['float-right', '#right'],
        ['max-w-xs', '#small'],
    ])('preserves %s layout and caption without serializing the overlay', (className, hint) => {
        const container = body(
            block(
                '![A taper](/storage/blog/taper.webp){.tryable}',
                `<figure class="${className}"><span class="blog-figure-frame">${PICTURE.replace(
                    'alt="A taper"',
                    'alt="A taper" data-gallery-tryable="/onboarding"'
                )}<a class="blog-figure-try blog-try-button blog-try-bottom-right" href="/onboarding">Try this on me</a></span><figcaption>Front view</figcaption></figure>`
            )
        )
        const snapshots = readBlocks(container)

        container.querySelector('img')!.alt = 'A low taper'

        expect(serializeBody(container, snapshots)).toBe(
            `![A low taper](/storage/blog/taper.webp${hint} "Front view"){.tryable}`
        )
    })

    it('writes a caption as the markdown title the renderer prints', () => {
        const container = body(
            block(
                '![A taper](/storage/blog/taper.webp "Front view")',
                `<figure>${PICTURE}<figcaption>Front view</figcaption></figure>`
            )
        )
        const snapshots = readBlocks(container)

        container.querySelector('figcaption')!.textContent = 'Three weeks in'

        expect(serializeBody(container, snapshots)).toBe(
            '![A taper](/storage/blog/taper.webp "Three weeks in")'
        )
    })
})

describe('text that looks like markup', () => {
    it('is escaped so it survives a round trip as text', () => {
        const container = body(block('Placeholder.', '<p>Placeholder.</p>'))
        const snapshots = readBlocks(container)

        container.querySelector('p')!.textContent = 'Ask for a 2 * 3 [taper] not `this`'

        expect(serializeBody(container, snapshots)).toBe(
            'Ask for a 2 \\* 3 \\[taper\\] not \\`this\\`'
        )
    })

    it('does not turn a stray blank formatting tag into literal asterisks', () => {
        const container = body(block('A sentence.', '<p>A sentence.</p>'))
        const snapshots = readBlocks(container)

        container.querySelector('p')!.innerHTML = 'A <strong> </strong>sentence.'

        expect(serializeBody(container, snapshots)).toBe('A sentence.')
    })
})

describe('blocks the editor added', () => {
    it('are serialised from the DOM, not swallowed by the block above them', () => {
        const container = body(block('The first paragraph.', '<p>The first paragraph.</p>', 0))

        const added = document.createElement('p')
        added.setAttribute('data-md-block', 'new-1')
        added.textContent = 'A second paragraph.'
        container.appendChild(added)

        expect(roundTrip(container)).toBe('The first paragraph.\n\nA second paragraph.')
    })
})

describe('locating a block', () => {
    it('finds the block a node sits inside', () => {
        const container = body(
            block('First.', '<p>First.</p>', 0) +
                block('- One\n- Two', '<ul><li>One</li><li>Two</li></ul>', 1)
        )

        const item = container.querySelector('li')!

        expect(blockGroupOf(container, item)?.id).toBe('1')
        expect(blockGroupOf(container, document.createElement('p'))).toBeNull()
    })
})
