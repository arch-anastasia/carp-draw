import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { initBlogEditor } from '@/blog/inlineEditor'
import { closePanel } from '@/blog/panels'
import { closeMenu } from '@/blog/menu'

/**
 * The editor, wired to the page.
 *
 * What is checked here is the contract between the toolbar and the server: that
 * nothing is editable or saveable until somebody asks, that a save carries only
 * the fields that changed and carries them under the names the API validates,
 * and that the article on screen is replaced by what the server rendered rather
 * than by what the browser had.
 *
 * The markup mirrors what x-blog.editor-toolbar and the post template print,
 * because those hooks are the contract too.
 */

const BOOTSTRAP = {
    post: {
        id: 'post-1',
        title: 'The Taper',
        slug: 'the-taper',
        url: '/blog/the-taper',
        status: 'draft',
        excerpt: 'A gradient, not a length.',
        targetKeyword: null,
        secondaryKeywords: [],
        metaTitle: null,
        metaDescription: null,
        audience: null,
        resolvedAudience: null,
        ctaText: null,
        ctaUrl: null,
        publishedAt: null,
        publishedAtLabel: null,
        datedAt: null,
        publishesAt: '1 Jan 2026',
        publishSchedules: false,
        coverUrl: '/storage/blog/cover.webp',
        coverPath: '/storage/blog/cover.webp',
        coverAlt: 'A taper',
        categories: [],
        authors: [],
        faq: [{ question: 'How long?', answer: 'Twenty seconds.' }],
        readingMinutes: 3,
        viewsCount: 0,
        updatedAt: null,
    },
    options: { categories: [], authors: [], audiences: [] },
    endpoints: {
        save: '/admin/posts/post-1/inline',
        image: '/admin/blog-images',
        destroy: '/admin/posts/post-1',
        publish: '/admin/posts/post-1/publish',
        admin: '/admin/posts/post-1/edit',
        index: '/admin/posts',
    },
    limits: { metaTitle: 255, metaDescription: 300, excerpt: 500, faqQuestions: 2 },
    revision: null,
    timezone: 'UTC',
    csrf: 'token-abc',
}

function encode(source: string): string {
    return btoa(String.fromCharCode(...new TextEncoder().encode(source)))
}

function render(): void {
    document.body.innerHTML = `
        <div data-blog-editor='${JSON.stringify(BOOTSTRAP)}' class="blog-editor-bar">
            <div class="editor-rail">
                <p class="editor-status">
                    <span class="editor-badge" data-editor-badge data-status="draft">Draft</span>
                    <span data-editor-message>Not published</span>
                </p>
                <div class="editor-actions" data-editor-actions hidden>
                    <button type="button" data-editor-toggle class="editor-button" aria-pressed="false" data-tip="Edit the article"><span class="sr-only" data-editor-toggle-label>Edit the article</span></button>
                    <button type="button" data-editor-panel-open="insert" aria-expanded="false" data-editor-while-editing hidden>Insert</button>
                    <button type="button" data-editor-panel-open="article" aria-expanded="false">Article</button>
                    <button type="button" data-editor-panel-open="seo" aria-expanded="false">SEO</button>
                    <button type="button" data-editor-panel-open="publishing" aria-expanded="false">Publishing</button>
                    <div class="editor-group" data-editor-commit hidden>
                        <button type="button" data-editor-discard disabled>Discard</button>
                        <button type="button" data-editor-save class="editor-button" data-tip="Save changes" disabled><span class="sr-only" data-editor-save-label>Save changes</span></button>
                    </div>
                </div>
                <form method="POST" action="/admin/posts/post-1/publish" data-editor-publish-form data-editor-fallback>
                    <button type="submit">Publish now</button>
                </form>
            </div>
            <p class="editor-hint" data-editor-hint hidden></p>
        </div>

        <h1 data-blog-editor-title>The Taper</h1>
        <div data-blog-editor-cover-empty hidden>Add a cover</div>
        <img data-blog-editor-cover src="/storage/blog/cover.webp" alt="A taper">

        <div data-article-body data-blog-editor-body>
            <p data-md-block="0" data-md-source="${encode('A gradient, not a length.')}">A gradient, not a length.</p>
            <picture data-md-block="1" data-md-source="${encode('![A taper](/storage/blog/taper.webp)')}"><img src="/storage/blog/taper.webp" alt="A taper" class="block mx-auto my-8 w-full rounded-box border border-base-300"></picture>
        </div>

        <section data-blog-editor-faq>
            <div data-faq-list>
                <div class="collapse" data-faq-item>
                    <div data-faq-question>How long?</div>
                    <div class="collapse-content"><p data-faq-answer>Twenty seconds.</p></div>
                </div>
            </div>
            <p data-faq-controls hidden><button type="button" data-faq-add>Add a question</button></p>
        </section>
    `
}

function saved(): { post: unknown; html: string; outline: unknown[] } {
    return {
        post: { ...BOOTSTRAP.post, title: 'The Low Taper' },
        html: `<p data-md-block="0" data-md-source="${encode('Rewritten by the server.')}">Rewritten by the server.</p>`,
        outline: [],
    }
}

let fetchMock: ReturnType<typeof vi.fn>

function requestBody(): Record<string, unknown> {
    return JSON.parse(fetchMock.mock.calls[0][1].body as string) as Record<string, unknown>
}

const body = (): HTMLElement => document.querySelector('[data-blog-editor-body]') as HTMLElement
const button = (hook: string): HTMLButtonElement =>
    document.querySelector(`[${hook}]`) as HTMLButtonElement

beforeEach(() => {
    render()

    fetchMock = vi.fn(() =>
        Promise.resolve({
            ok: true,
            status: 200,
            json: () => Promise.resolve(saved()),
        })
    )

    vi.stubGlobal('fetch', fetchMock)
    initBlogEditor()
})

afterEach(() => {
    /* One panel and one menu at a time, and that "one" outlives a fixture teardown. */
    closePanel()
    closeMenu()
    vi.unstubAllGlobals()
    document.body.innerHTML = ''
})

describe('the toolbar', () => {
    it('appears only once the editor is running', () => {
        expect(document.querySelector<HTMLElement>('[data-editor-actions]')?.hidden).toBe(false)
        expect(button('data-editor-save').disabled).toBe(true)
        expect(button('data-editor-discard').disabled).toBe(true)
    })

    /* The panel that publishes is the bundle's job; the form is the fallback. */
    it('takes the no-JavaScript publish form off the bar', () => {
        expect(document.querySelector<HTMLElement>('[data-editor-fallback]')?.hidden).toBe(true)
    })

    it('holds Save and Discard back until there is something to save', () => {
        const commit = document.querySelector<HTMLElement>('[data-editor-commit]') as HTMLElement

        expect(commit.hidden).toBe(true)

        button('data-editor-toggle').click()
        body().querySelector('p')!.textContent = 'A rewritten sentence.'
        body().dispatchEvent(new Event('input', { bubbles: true }))

        expect(commit.hidden).toBe(false)
        expect(button('data-editor-save').disabled).toBe(false)
    })

    it('leaves the article alone until editing is turned on', () => {
        expect(body().getAttribute('contenteditable')).not.toBe('true')

        button('data-editor-toggle').click()

        expect(body().getAttribute('contenteditable')).toBe('true')
        expect(button('data-editor-toggle').getAttribute('aria-pressed')).toBe('true')
        expect(
            document.querySelector('[data-blog-editor-faq]')?.classList.contains('blog-faq-editing')
        ).toBe(true)
    })

    /*
     * The icon stays; only the name under it changes. Setting textContent here
     * - which is what the worded bar did - would have replaced the mark with
     * the word.
     */
    it('renames the edit button without emptying it', () => {
        const toggle = button('data-editor-toggle')

        expect(toggle.dataset.tip).toBe('Edit the article')

        toggle.click()

        expect(toggle.dataset.tip).toBe('Stop editing')
        expect(toggle.querySelector('[data-editor-toggle-label]')?.textContent).toBe('Stop editing')
    })

    it('offers the insert button only while editing', () => {
        const insert = document.querySelector<HTMLElement>(
            '[data-editor-panel-open="insert"]'
        ) as HTMLElement

        expect(insert.hidden).toBe(true)

        button('data-editor-toggle').click()
        expect(insert.hidden).toBe(false)

        button('data-editor-toggle').click()
        expect(insert.hidden).toBe(true)
    })

    it('opens a panel and closes it again', () => {
        const open = button('data-editor-panel-open')

        open.click()
        expect(document.querySelector('.blog-editor-panel')).not.toBeNull()
        expect(open.getAttribute('aria-expanded')).toBe('true')

        open.click()
        expect(document.querySelector('.blog-editor-panel')).toBeNull()
    })
})

describe('an open panel', () => {
    const open = (): HTMLButtonElement => {
        const anchor = document.querySelector<HTMLButtonElement>(
            '[data-editor-panel-open="article"]'
        ) as HTMLButtonElement
        anchor.click()

        return anchor
    }

    const pointerDownOn = (target: EventTarget): void => {
        target.dispatchEvent(new Event('pointerdown', { bubbles: true }))
    }

    it('closes on a click anywhere else on the page', () => {
        const anchor = open()

        pointerDownOn(document.querySelector('[data-blog-editor-body]') as HTMLElement)

        expect(document.querySelector('.blog-editor-panel')).toBeNull()
        expect(anchor.getAttribute('aria-expanded')).toBe('false')
    })

    it('survives a click inside itself, and inside the bar that opened it', () => {
        open()

        pointerDownOn(document.querySelector('.blog-editor-panel input') as HTMLElement)
        expect(document.querySelector('.blog-editor-panel')).not.toBeNull()

        pointerDownOn(document.querySelector('.editor-status') as HTMLElement)
        expect(document.querySelector('.blog-editor-panel')).not.toBeNull()
    })

    it('closes on Escape', () => {
        open()

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))

        expect(document.querySelector('.blog-editor-panel')).toBeNull()
    })

    it('closes from the cross in its corner', () => {
        open()

        document.querySelector<HTMLButtonElement>('.panel-close')!.click()

        expect(document.querySelector('.blog-editor-panel')).toBeNull()
    })

    /* The closing offer lost its own panel and became a section of this one. */
    it('holds the ending under the article it closes', () => {
        open()

        const labels = Array.from(
            document.querySelectorAll<HTMLElement>('.blog-editor-panel label')
        ).map((label) => label.textContent)

        expect(labels).toContain('Headline')
        expect(labels).toContain('Button text')
        expect(labels).toContain('Button URL')
    })
})

describe('a save', () => {
    it('is refused while nothing has changed', async () => {
        button('data-editor-save').click()
        await Promise.resolve()

        expect(fetchMock).not.toHaveBeenCalled()
    })

    it('carries the prose, under the name the API validates', async () => {
        button('data-editor-toggle').click()

        body().querySelector('p')!.textContent = 'A rewritten sentence.'
        body().dispatchEvent(new Event('input', { bubbles: true }))

        expect(button('data-editor-save').disabled).toBe(false)

        button('data-editor-save').click()
        await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1))

        const [url, options] = fetchMock.mock.calls[0] as [string, RequestInit]

        expect(url).toBe('/admin/posts/post-1/inline')
        expect(options.method).toBe('PATCH')
        expect((options.headers as Record<string, string>)['X-CSRF-TOKEN']).toBe('token-abc')
        expect(requestBody()).toEqual({
            body: 'A rewritten sentence.\n\n![A taper](/storage/blog/taper.webp)',
        })
    })

    it('carries a retitled headline and nothing else', async () => {
        button('data-editor-toggle').click()

        const title = document.querySelector('[data-blog-editor-title]') as HTMLElement
        title.textContent = 'The Low Taper'
        title.dispatchEvent(new Event('input', { bubbles: true }))

        expect(button('data-editor-save').disabled).toBe(false)

        button('data-editor-save').click()
        await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1))

        expect(requestBody()).toEqual({ title: 'The Low Taper' })
    })

    it('carries the questions as questions, not as prose', async () => {
        button('data-editor-toggle').click()

        document.querySelector('[data-faq-answer]')!.textContent = 'About twenty seconds.'
        document
            .querySelector('[data-blog-editor-faq]')!
            .dispatchEvent(new Event('input', { bubbles: true }))

        button('data-editor-save').click()
        await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1))

        expect(requestBody()).toEqual({
            faq: [{ question: 'How long?', answer: 'About twenty seconds.' }],
        })
    })

    it('takes the article back from the server rather than keeping what was typed', async () => {
        button('data-editor-toggle').click()

        body().querySelector('p')!.textContent = 'What the browser had.'
        body().dispatchEvent(new Event('input', { bubbles: true }))

        button('data-editor-save').click()
        await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1))
        await vi.waitFor(() => expect(body().textContent).toContain('Rewritten by the server.'))

        expect(document.querySelector('[data-blog-editor-title]')?.textContent).toBe(
            'The Low Taper'
        )
        expect(button('data-editor-save').disabled).toBe(true)
    })

    it('reports a refusal and keeps the edit', async () => {
        fetchMock.mockResolvedValue({
            ok: false,
            status: 422,
            json: () => Promise.resolve({ message: 'The body needs a FAQ section.' }),
        })

        button('data-editor-toggle').click()
        body().querySelector('p')!.textContent = 'Half an article.'
        body().dispatchEvent(new Event('input', { bubbles: true }))

        button('data-editor-save').click()
        await vi.waitFor(() =>
            expect(document.querySelector('[data-editor-hint]')?.textContent).toBe(
                'The body needs a FAQ section.'
            )
        )

        expect(document.querySelector('[data-editor-hint]')?.getAttribute('data-tone')).toBe(
            'error'
        )
        /* Still dirty: nothing was stored, so nothing should look stored. */
        expect(button('data-editor-save').disabled).toBe(false)
    })
})

describe('the cover', () => {
    it('can add a first cover, edit it, remove it and add it again', async () => {
        document.querySelector('[data-blog-editor-cover]')!.remove()
        const slot = document.querySelector<HTMLElement>('[data-blog-editor-cover-empty]')!
        slot.hidden = false
        fetchMock.mockImplementation(() => Promise.resolve({
            ok: true, status: 200, json: () => Promise.resolve({data: {
                id: 'new-cover', url: '/storage/blog/new.webp', alt: 'Oak chair',
                width: 800, height: 600, mime: 'image/webp', bytes: 100, markdown: '',
            }}),
        }))
        for (let attempt = 0; attempt < 2; attempt++) {
            slot.click()
            const fileInput = document.querySelector<HTMLInputElement>('input[type="file"]')!
            Object.defineProperty(fileInput, 'files', {value: [new File(['test'], 'chair.webp', {type: 'image/webp'})]})
            fileInput.dispatchEvent(new Event('change'))
            await vi.waitFor(() => expect(document.querySelector('[data-blog-editor-cover]')).not.toBeNull())
            expect(slot.hidden).toBe(true)
            const cover = document.querySelector<HTMLImageElement>('[data-blog-editor-cover]')!
            expect(cover.width).toBe(800)
            cover.dispatchEvent(new MouseEvent('contextmenu', {bubbles: true}))
            const remove = Array.from(document.querySelectorAll<HTMLButtonElement>('.blog-image-menu button'))
                .find((item) => item.textContent === 'Remove cover')!
            expect(remove).toBeDefined()
            remove.click()
            expect(document.querySelector('[data-blog-editor-cover]')).toBeNull()
            expect(slot.hidden).toBe(false)
        }
    })

    it('sends edited cover alt text with the save', async () => {
        document.querySelector('[data-blog-editor-cover]')!.dispatchEvent(
            new MouseEvent('contextmenu', { bubbles: true })
        )
        const altButton = Array.from(document.querySelectorAll<HTMLButtonElement>('.blog-image-menu button'))
            .find((item) => item.textContent === 'Alt text…')
        expect(altButton).toBeDefined()
        altButton!.click()
        const input = document.querySelector<HTMLInputElement>('#blog-dialog-alt')!
        input.value = 'Oak chair with a curved back'
        const confirm = Array.from(document.querySelectorAll<HTMLButtonElement>('.dialog-actions button'))
            .find((item) => item.textContent === 'Save')!
        confirm.click()
        await vi.waitFor(() => expect(button('data-editor-save').disabled).toBe(false))
        button('data-editor-save').click()
        await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1))
        expect(requestBody()).toEqual({
            cover_image_path: '/storage/blog/cover.webp',
            cover_image_alt: 'Oak chair with a curved back',
        })
    })

    it('leaves the page and the article together', async () => {
        ;(document.querySelector('[data-editor-panel-open="seo"]') as HTMLButtonElement).click()

        const remove = Array.from(
            document.querySelectorAll<HTMLButtonElement>('.panel-button')
        ).find((candidate) => candidate.textContent === 'Remove cover')

        expect(remove).toBeDefined()
        remove!.click()

        expect(document.querySelector('[data-blog-editor-cover]')).toBeNull()
        expect(document.querySelector<HTMLElement>('[data-blog-editor-cover-empty]')?.hidden).toBe(false)

        button('data-editor-save').click()
        await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1))

        expect(requestBody()).toEqual({ cover_image_path: null })
    })

    it('offers an upload when the article has none', () => {
        ;(document.querySelector('[data-editor-panel-open="seo"]') as HTMLButtonElement).click()

        const labels = Array.from(
            document.querySelectorAll<HTMLButtonElement>('.panel-button')
        ).map((candidate) => candidate.textContent)

        expect(labels).toContain('Replace cover')
    })
})

describe('a picture', () => {
    it('keeps the try-on illustration out of the article image menu', () => {
        body().innerHTML = '<aside class="blog-try-on blog-article-cta"><div><img src="/images/demo/noah-before.webp" alt="Before"></div></aside>'

        for (const target of Array.from(body().querySelectorAll('img, div'))) {
            target.dispatchEvent(new MouseEvent('contextmenu', { bubbles: true }))
        }

        expect(document.querySelector('.blog-image-menu')).toBeNull()
        expect(body().getAttribute('contenteditable')).not.toBe('true')
    })

    const image = (): HTMLImageElement =>
        document.querySelector('[data-blog-editor-body] img') as HTMLImageElement

    const menuItem = (label: string): HTMLButtonElement | undefined =>
        Array.from(document.querySelectorAll<HTMLButtonElement>('.blog-image-menu button')).find(
            (candidate) => candidate.textContent === label
        )

    function rightClick(): void {
        image().dispatchEvent(
            new MouseEvent('contextmenu', { bubbles: true, clientX: 10, clientY: 10 })
        )
    }

    it('opens its menu on a right-click, and turns editing on with it', () => {
        expect(body().getAttribute('contenteditable')).not.toBe('true')

        rightClick()

        expect(document.querySelector('.blog-image-menu')).not.toBeNull()
        expect(body().getAttribute('contenteditable')).toBe('true')
        expect(menuItem('Replace image…')).toBeDefined()
        expect(menuItem('Crop image…')).toBeDefined()
        expect(menuItem('Remove image')).toBeDefined()
    })

    it('ticks the alignment it already has, and rewrites the hint when another is picked', async () => {
        rightClick()

        expect(menuItem('Full width')?.getAttribute('aria-checked')).toBe('true')
        expect(menuItem('Float left')?.getAttribute('aria-checked')).toBe('false')

        menuItem('Float left')!.click()

        expect(image().dataset.mdHint).toBe('left')
        expect(image().className).toContain('float-left')

        button('data-editor-save').click()
        await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1))

        expect(requestBody().body).toBe(
            'A gradient, not a length.\n\n![A taper](/storage/blog/taper.webp#left)'
        )
    })

    it('moves up the article, and the markdown moves with it', async () => {
        rightClick()

        expect(menuItem('Move down')?.disabled).toBe(true)
        menuItem('Move up')!.click()

        button('data-editor-save').click()
        await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1))

        /* The picture is now first, and the paragraph it passed is untouched. */
        expect(requestBody().body).toBe(
            '![A taper](/storage/blog/taper.webp)\n\nA gradient, not a length.'
        )
    })

    it('is removed with its block, leaving the prose alone', async () => {
        rightClick()
        menuItem('Remove image')!.click()

        expect(document.querySelector('[data-blog-editor-body] img')).toBeNull()

        button('data-editor-save').click()
        await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1))

        expect(requestBody().body).toBe('A gradient, not a length.')
    })
})

describe('the publishing panel', () => {
    const open = (): void => {
        ;(
            document.querySelector('[data-editor-panel-open="publishing"]') as HTMLButtonElement
        ).click()
    }

    const panelButton = (label: string): HTMLButtonElement | undefined =>
        Array.from(document.querySelectorAll<HTMLButtonElement>('.panel-button')).find(
            (candidate) => candidate.textContent === label
        )

    /*
     * The bug: the button used to read the clock the browser was set to and
     * send it as a bare `2026-09-12T09:30`, which the server reads in the site's
     * own timezone. Pressed on a phone three hours east, "Publish now" asked for
     * three hours from now, and the article stayed dark and marked Scheduled.
     *
     * The assertions are deliberately zone-agnostic - CI and a laptop are not
     * in the same one - so they check the two properties that make the string
     * safe to hand over: it names an offset, and it is this instant.
     */
    it('publishes at an instant rather than at the clock the browser is set to', async () => {
        open()

        const before = Date.now()
        panelButton('Publish now')!.click()
        await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1))

        const sent = requestBody().published_at as string

        expect(sent).toMatch(/(Z|[+-]\d{2}:\d{2})$/)
        expect(Date.parse(sent)).toBeGreaterThanOrEqual(before)
        expect(Date.parse(sent)).toBeLessThanOrEqual(Date.now())
    })

    /* A datetime-local input cannot say whose clock it means, so the help does. */
    it('names the clock its two dates are written in', () => {
        open()

        const help = Array.from(document.querySelectorAll('.panel-help')).map(
            (note) => note.textContent ?? ''
        )

        expect(help.filter((note) => note.includes('Site time (UTC)'))).toHaveLength(2)
    })
})
