import { beforeEach, describe, expect, it, vi } from 'vitest'
import { initImageGalleries } from '@/blog/media'

/*
 * An article as the server renders one: a cover, a picture in the prose and a
 * gallery, all inside the article's scope, with the gallery declaring a scope
 * of its own. The viewer has to read that as one set of four.
 */
const ARTICLE = `
    <article data-image-gallery>
        <img src="/cover.webp" alt="Cover" data-gallery-image data-gallery-src="/cover.webp" data-gallery-caption="" tabindex="0" role="button">
        <figure><span class="blog-figure-frame"><picture><img src="/inline.webp" alt="Inline" data-gallery-image data-gallery-src="/inline.webp" data-gallery-caption="In the prose" tabindex="0" role="button"></picture><a class="blog-figure-try blog-try-button blog-try-bottom-right" href="/?from=blog_prose_image" data-track-cta="blog_prose_image" data-track-cta-variant="figure">Explore CarpDraw</a></span></figure>
        <div class="blog-gallery" data-image-gallery>
            <div class="blog-gallery-grid">
                <figure class="blog-gallery-item"><span class="blog-gallery-frame">
                    <img src="/one.webp" alt="First" data-gallery-image data-gallery-src="/one.webp" data-gallery-caption="One" data-gallery-tryable="/" tabindex="0" role="button">
                    <a class="blog-gallery-try blog-try-button blog-try-bottom-right" href="/?from=blog_gallery" data-track-cta="blog_gallery" data-track-cta-variant="tile">Explore CarpDraw</a>
                </span></figure>
                <figure class="blog-gallery-item"><span class="blog-gallery-frame">
                    <img src="/two.webp" alt="Second" data-gallery-image data-gallery-src="/two.webp" data-gallery-caption="Two" tabindex="0" role="button">
                </span></figure>
            </div>
        </div>
    </article>`

beforeEach(() => {
    document.body.innerHTML = ARTICLE
    document.documentElement.style.overflow = ''
    vi.stubGlobal('matchMedia', () => ({ matches: false, addEventListener: () => {} }))
    Element.prototype.scrollIntoView = vi.fn()
})

function fullImage(): HTMLImageElement {
    return document.querySelector<HTMLImageElement>('[data-gallery-full]') as HTMLImageElement
}

function openers(): HTMLElement[] {
    return Array.from(document.querySelectorAll<HTMLElement>('article [data-gallery-image]'))
}

function open(src: string): HTMLElement {
    const opener = openers().find((image) => image.dataset.gallerySrc === src) as HTMLElement
    opener.click()

    return opener
}

describe('image gallery lightbox', () => {
    function addTopGrid(): HTMLButtonElement[] {
        document.querySelector('article > img')?.insertAdjacentHTML(
            'afterend',
            `<div data-blog-top-grid>${['/one.webp', '/two.webp', '/inline.webp']
                .map(
                    (src) =>
                        `<button type="button" data-gallery-open data-gallery-src="${src}" data-track-cta="blog_top_grid"><img src="${src}" alt=""></button>`
                )
                .join('')}</div>`
        )

        return Array.from(document.querySelectorAll<HTMLButtonElement>('[data-gallery-open]'))
    }

    it.each([
        [0, '/one.webp', '3 of 4'],
        [1, '/two.webp', '4 of 4'],
        [2, '/inline.webp', '2 of 4'],
    ])('top thumbnail %i opens its existing article index', (index, src, counter) => {
        const buttons = addTopGrid()
        initImageGalleries()
        buttons[index]?.querySelector('img')?.click()

        expect(fullImage().getAttribute('src')).toContain(src)
        expect(document.querySelector('[data-gallery-counter]')?.textContent).toBe(counter)
        expect(document.querySelectorAll('.gallery-lightbox-thumb')).toHaveLength(4)
    })

    it('keeps the cover first and the tryable offer intact after adding top thumbnails', () => {
        const buttons = addTopGrid()
        initImageGalleries()
        open('/cover.webp')
        expect(document.querySelector('[data-gallery-counter]')?.textContent).toBe('1 of 4')

        buttons[0]?.click()
        expect(document.querySelector('[data-gallery-shown-caption]')?.textContent).toBe('One')
        expect(document.querySelector<HTMLAnchorElement>('[data-gallery-try]')?.hidden).toBe(false)

        document.querySelector<HTMLButtonElement>('[data-gallery-close]')?.click()
        expect(document.activeElement).toBe(buttons[0])
    })

    it('allows native button keyboard activation to dispatch the tracked click', () => {
        const buttons = addTopGrid()
        initImageGalleries()
        const event = new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true })
        buttons[0]?.dispatchEvent(event)

        expect(event.defaultPrevented).toBe(false)
        buttons[0]?.click()
        expect(fullImage().getAttribute('src')).toContain('/one.webp')
    })

    it('ignores a thumbnail whose source is no longer in the article', () => {
        const buttons = addTopGrid()
        buttons[0]!.dataset.gallerySrc = '/missing.webp'
        initImageGalleries()
        buttons[0]?.click()

        expect(document.querySelector('[data-gallery-dialog]')).toBeNull()
    })

    it('spans the cover, the prose and the gallery as one strip in reading order', () => {
        initImageGalleries()
        open('/cover.webp')

        const counter = document.querySelector<HTMLElement>('[data-gallery-counter]')
        expect(counter?.textContent).toBe('1 of 4')
        expect(document.querySelectorAll('.gallery-lightbox-thumb')).toHaveLength(4)

        document.querySelector<HTMLButtonElement>('[data-gallery-next]')?.click()
        expect(fullImage().getAttribute('src')).toContain('/inline.webp')
        expect(document.querySelector('[data-gallery-shown-caption]')?.textContent).toBe(
            'In the prose'
        )
    })

    it('opens a gallery tile at its own place in the whole article set', () => {
        initImageGalleries()
        open('/two.webp')

        expect(document.querySelector<HTMLElement>('[data-gallery-counter]')?.textContent).toBe(
            '4 of 4'
        )
    })

    it.each([
        ['.blog-gallery-try', '/?from=blog_gallery'],
        ['.blog-figure-try', '/?from=blog_prose_image'],
    ])('keeps %s as a visible link without opening the viewer', (selector, href) => {
        initImageGalleries()

        const offer = document.querySelector<HTMLAnchorElement>(selector)!
        expect(offer.textContent).toBe('Explore CarpDraw')
        expect(offer.getAttribute('href')).toBe(href)
        expect(offer.classList.contains('blog-try-bottom-right')).toBe(true)
        expect(offer.closest('figure')).not.toBeNull()
        expect(offer.hasAttribute('data-track-cta')).toBe(true)
        offer.click()

        expect(document.querySelector('[data-gallery-dialog]')).toBeNull()
    })

    it('next and previous wrap around the ends', () => {
        initImageGalleries()
        open('/cover.webp')

        document.querySelector<HTMLButtonElement>('[data-gallery-prev]')?.click()
        expect(fullImage().getAttribute('src')).toContain('/two.webp')
        document.querySelector<HTMLButtonElement>('[data-gallery-next]')?.click()
        expect(fullImage().getAttribute('src')).toContain('/cover.webp')
    })

    it('a thumbnail jumps straight to its picture', () => {
        initImageGalleries()
        open('/cover.webp')

        document.querySelectorAll<HTMLButtonElement>('.gallery-lightbox-thumb')[2]?.click()

        expect(fullImage().getAttribute('src')).toContain('/one.webp')
        expect(
            document
                .querySelectorAll('.gallery-lightbox-thumb')[2]
                ?.classList.contains('is-current')
        ).toBe(true)
    })

    it('the offer shows only on a tryable image and links to plain onboarding', () => {
        initImageGalleries()
        open('/cover.webp')

        const offer = document.querySelector<HTMLAnchorElement>('[data-gallery-try]')
        const band = document.querySelector<HTMLElement>('[data-gallery-offer]')
        expect(offer?.hidden).toBe(true)
        /* The band goes with it, or its label stands over an empty footer. */
        expect(band?.hidden).toBe(true)

        document.querySelectorAll<HTMLButtonElement>('.gallery-lightbox-thumb')[2]?.click()
        expect(offer?.hidden).toBe(false)
        expect(band?.hidden).toBe(false)
        expect(offer?.getAttribute('href')).toBe('/')
        expect(offer?.classList.contains('blog-try-button')).toBe(true)
        expect(document.querySelectorAll('.gallery-lightbox-thumb-flag')).toHaveLength(1)
    })

    it('the offer is a plain link into the regular onboarding', () => {
        initImageGalleries()
        const opener = open('/one.webp')

        document.querySelector<HTMLAnchorElement>('[data-gallery-try]')?.click()

        // The selected picture is not passed to onboarding - only which
        // call to action this was.
        expect(
            document.querySelector<HTMLAnchorElement>('[data-gallery-try]')?.getAttribute('href')
        ).toBe('/')
        expect(opener.hasAttribute('data-onboarding-cta')).toBe(false)
    })

    it('Escape closes and returns focus to the picture that was clicked', () => {
        initImageGalleries()
        const opener = open('/two.webp')

        document
            .querySelector<HTMLElement>('[data-gallery-dialog]')
            ?.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))

        expect(document.querySelector('[data-gallery-dialog]')).toBeNull()
        expect(document.activeElement).toBe(opener)
        expect(document.documentElement.style.overflow).toBe('')
    })

    it('zoom in then reset restores the original scale, and the button reads it back', () => {
        initImageGalleries()
        open('/one.webp')

        const level = document.querySelector<HTMLElement>('[data-gallery-zoom-level]')
        const dialog = document.querySelector<HTMLElement>('[data-gallery-dialog]')
        expect(level?.textContent).toBe('100%')

        document.querySelector<HTMLButtonElement>('[data-gallery-zoom-in]')?.click()
        expect(fullImage().dataset.galleryScale).toBe('1.5')
        expect(level?.textContent).toBe('150%')
        expect(dialog?.classList.contains('is-zoomed')).toBe(true)

        document.querySelector<HTMLButtonElement>('[data-gallery-reset]')?.click()
        expect(fullImage().dataset.galleryScale).toBe('1')
        expect(level?.textContent).toBe('100%')
        expect(dialog?.classList.contains('is-zoomed')).toBe(false)
    })

    it('the ground around the picture closes, the picture and the chrome do not', () => {
        initImageGalleries()
        open('/one.webp')

        fullImage().click()
        expect(document.querySelector('[data-gallery-dialog]')).not.toBeNull()

        document.querySelector<HTMLElement>('.gallery-lightbox-view')?.click()
        expect(document.querySelector('[data-gallery-dialog]')).toBeNull()
    })

    it('binding twice attaches one listener and opens one dialog', () => {
        initImageGalleries()
        initImageGalleries()
        open('/one.webp')

        expect(document.querySelectorAll('[data-gallery-dialog]')).toHaveLength(1)
    })

    it('picks up pictures added to a bound scope after an editor save', () => {
        initImageGalleries()
        const article = document.querySelector('article') as HTMLElement
        article.insertAdjacentHTML(
            'beforeend',
            '<img src="/late.webp" alt="Late" data-gallery-image data-gallery-src="/late.webp" data-gallery-caption="Late" tabindex="0" role="button">'
        )

        open('/late.webp')

        expect(fullImage().getAttribute('src')).toContain('/late.webp')
        expect(document.querySelector<HTMLElement>('[data-gallery-counter]')?.textContent).toBe(
            '5 of 5'
        )
    })

    it('leaves a picture alone while the inline editor has the body open', () => {
        initImageGalleries()
        const article = document.querySelector('article') as HTMLElement
        article.setAttribute('contenteditable', 'true')

        open('/one.webp')

        expect(document.querySelector('[data-gallery-dialog]')).toBeNull()
    })
})
