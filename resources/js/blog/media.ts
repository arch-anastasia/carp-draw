const GALLERY_MIN_SCALE = 1
const GALLERY_MAX_SCALE = 4
const GALLERY_SCALE_STEP = 0.5
/** How far a finger travels before the viewer treats it as turning the page. */
const GALLERY_SWIPE_PX = 60
/** The viewer's offer, as both its analytics source and its onboarding `from`. */
/*
 * The viewer's chrome, drawn rather than typed: the glyphs `&#8249;` and
 * `&#215;` render at whatever weight the UI face happens to give them, which
 * on Space Grotesk is a hairline that disappears against a photograph. These
 * are stroked to the same weight as the brand's corner ticks.
 */
const GALLERY_ICONS = {
    close: '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>',
    previous:
        '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>',
    next: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>',
    forward:
        '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12h15M13 6l6 6-6 6"/></svg>',
}
function bindOnce(element: HTMLElement, feature: string): boolean {
    const key = `carpdrawBound${feature}`

    if (element.dataset[key] === '1') {
        return false
    }

    element.dataset[key] = '1'

    return true
}

function queryAll<T extends HTMLElement>(selector: string): T[] {
    return Array.from(document.querySelectorAll<T>(selector))
}

interface GalleryItem {
    src: string
    alt: string
    caption: string
    tryUrl: string | null
}

/**
 * The viewer's set is every [data-gallery-image] inside a scope, in document
 * order - so on an article that is the cover, the pictures in the prose and
 * every gallery, as one strip a reader can page through. A scope inside
 * another scope is ignored: the blog post declares one on the article, and
 * the gallery blocks it contains declare their own so the same markup still
 * works on its own in the editor's preview.
 */
function galleryScopes(): HTMLElement[] {
    return queryAll<HTMLElement>('[data-image-gallery]').filter(
        (scope) => !scope.parentElement?.closest('[data-image-gallery]')
    )
}

/**
 * Delegated rather than bound image by image: an editor save replaces the
 * article's HTML underneath a scope that is already bound, and listeners
 * attached to the old pictures would go with them.
 */
export function initImageGalleries(): void {
    galleryScopes().forEach((scope) => {
        if (!bindOnce(scope, 'ImageGallery')) {
            return
        }

        scope.addEventListener('click', (event) => {
            const opener = openerFor(event.target)

            if (opener) {
                openImageGalleryFrom(scope, opener)
            }
        })

        scope.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return
            }

            const opener = openerFor(event.target)

            if (opener && opener.tagName !== 'BUTTON') {
                event.preventDefault()
                openImageGalleryFrom(scope, opener)
            }
        })
    })
}

/**
 * A picture being written is not a picture being read: while the inline
 * editor has the body open, a click belongs to whoever is editing it.
 */
function openerFor(target: EventTarget | null): HTMLElement | null {
    const opener = (target as HTMLElement | null)?.closest?.<HTMLElement>(
        '[data-gallery-image], [data-gallery-open]'
    )

    return opener && !opener.closest('[contenteditable="true"]') ? opener : null
}

function galleryItems(scope: HTMLElement): GalleryItem[] {
    const seen = new Set<string>()

    return Array.from(scope.querySelectorAll<HTMLElement>('[data-gallery-image]')).flatMap(
        (image) => {
            const src = gallerySrc(image)

            // The cover often runs again inside the article. It is one
            // picture to a reader, so it takes one place in the strip.
            if (src === '' || seen.has(src)) {
                return []
            }

            seen.add(src)

            return [
                {
                    src,
                    alt: image.getAttribute('alt') ?? '',
                    caption: image.dataset.galleryCaption ?? '',
                    tryUrl: image.dataset.galleryTryable || null,
                },
            ]
        }
    )
}

function gallerySrc(image: HTMLElement): string {
    return image.dataset.gallerySrc || image.getAttribute('src') || ''
}

function openImageGalleryFrom(scope: HTMLElement, opener: HTMLElement): void {
    const items = galleryItems(scope)
    const src = gallerySrc(opener)
    const index = items.findIndex((item) => item.src === src)

    if (index !== -1) {
        openImageGallery(items, index, opener)
    }
}

function openImageGallery(items: GalleryItem[], initialIndex: number, opener: HTMLElement): void {
    document.querySelector('[data-gallery-dialog]')?.remove()

    const dialog = document.createElement('div')
    dialog.className = 'gallery-lightbox'
    dialog.dataset.galleryDialog = ''
    dialog.setAttribute('role', 'dialog')
    dialog.setAttribute('aria-modal', 'true')
    dialog.setAttribute('aria-label', 'Image gallery')
    dialog.innerHTML = `
        <div class="gallery-lightbox-sheet">
            <header class="gallery-lightbox-bar">
                <p class="gallery-lightbox-index">
                    <span class="eyebrow">Plate</span>
                    <span data-gallery-counter></span>
                </p>
                <button type="button" class="gallery-lightbox-close" data-gallery-close aria-label="Close gallery">${GALLERY_ICONS.close}</button>
            </header>
            <div class="gallery-lightbox-view">
                <div class="gallery-lightbox-stage" data-gallery-stage>
                    <img data-gallery-full alt="">
                </div>
                <button type="button" class="gallery-lightbox-nav gallery-lightbox-prev" data-gallery-prev aria-label="Previous image">${GALLERY_ICONS.previous}</button>
                <button type="button" class="gallery-lightbox-nav gallery-lightbox-next" data-gallery-next aria-label="Next image">${GALLERY_ICONS.next}</button>
                <div class="gallery-lightbox-zoom" role="group" aria-label="Zoom">
                    <button type="button" data-gallery-zoom-out aria-label="Zoom out">&#8722;</button>
                    <button type="button" data-gallery-reset aria-label="Reset zoom"><span data-gallery-zoom-level>100%</span></button>
                    <button type="button" data-gallery-zoom-in aria-label="Zoom in">+</button>
                </div>
            </div>
            <div class="gallery-lightbox-footer">
                <p class="gallery-lightbox-caption" data-gallery-shown-caption></p>
                <div class="gallery-lightbox-offer" data-gallery-offer hidden>
                    <span class="gallery-lightbox-offer-note">Plan your next piece</span>
                    <a class="gallery-lightbox-try blog-try-button" data-gallery-try hidden>Explore CarpDraw${GALLERY_ICONS.forward}</a>
                </div>
            </div>
            <div class="gallery-lightbox-strip" data-gallery-strip role="group" aria-label="Every picture in this article"></div>
        </div>`
    document.body.appendChild(dialog)

    const image = dialog.querySelector<HTMLImageElement>('[data-gallery-full]') as HTMLImageElement
    const stage = dialog.querySelector<HTMLElement>('[data-gallery-stage]') as HTMLElement
    const view = dialog.querySelector<HTMLElement>('.gallery-lightbox-view') as HTMLElement
    const caption = dialog.querySelector<HTMLElement>('[data-gallery-shown-caption]') as HTMLElement
    const counter = dialog.querySelector<HTMLElement>('[data-gallery-counter]') as HTMLElement
    const tryLink = dialog.querySelector<HTMLAnchorElement>(
        '[data-gallery-try]'
    ) as HTMLAnchorElement
    const offer = dialog.querySelector<HTMLElement>('[data-gallery-offer]') as HTMLElement
    const zoomLevel = dialog.querySelector<HTMLElement>('[data-gallery-zoom-level]') as HTMLElement
    const strip = dialog.querySelector<HTMLElement>('[data-gallery-strip]') as HTMLElement
    const closeButton = dialog.querySelector<HTMLButtonElement>(
        '[data-gallery-close]'
    ) as HTMLButtonElement
    const navigation = dialog.querySelectorAll<HTMLButtonElement>(
        '[data-gallery-prev], [data-gallery-next]'
    )
    const previousOverflow = document.documentElement.style.overflow
    const thumbs = buildGalleryStrip(strip, items, (nextIndex) => show(nextIndex))
    let index = initialIndex
    let scale = GALLERY_MIN_SCALE
    let x = 0
    let y = 0
    let dragging = false
    let pointerX = 0
    let pointerY = 0
    let swipeX = 0
    let swipeY = 0
    const touches = new Map<number, PointerEvent>()
    let pinchDistance = 0

    navigation.forEach((button) => (button.hidden = items.length === 1))
    strip.hidden = items.length === 1
    document.documentElement.style.overflow = 'hidden'

    const paintTransform = (): void => {
        image.style.transform = `translate(${x}px, ${y}px) scale(${scale})`
        image.dataset.galleryScale = String(scale)
        /* The reset button is its own readout: a lone "Reset" says nothing
         * about how far in the picture already is. */
        zoomLevel.textContent = `${Math.round(scale * 100)}%`
        dialog.classList.toggle('is-zoomed', scale > GALLERY_MIN_SCALE)
    }
    const resetZoom = (): void => {
        scale = GALLERY_MIN_SCALE
        x = 0
        y = 0
        paintTransform()
    }
    const show = (nextIndex: number): void => {
        index = (nextIndex + items.length) % items.length
        const item = items[index]
        image.src = item.src
        image.alt = item.alt
        caption.textContent = item.caption
        caption.hidden = item.caption === ''
        counter.textContent = `${index + 1} of ${items.length}`
        offer.hidden = item.tryUrl === null
        tryLink.hidden = item.tryUrl === null
        tryLink.href = item.tryUrl === null ? '#' : item.tryUrl

        /* Restarted by hand: the class is already on the element from the
         * picture before, and an animation only replays from a fresh one. */
        image.classList.remove('is-entering')
        image.getBoundingClientRect()
        image.classList.add('is-entering')

        thumbs.forEach((thumb, at) => {
            thumb.classList.toggle('is-current', at === index)
            thumb.setAttribute('aria-current', at === index ? 'true' : 'false')
        })
        thumbs[index]?.scrollIntoView({ block: 'nearest', inline: 'center' })

        resetZoom()
    }
    const zoom = (amount: number): void => {
        scale = Math.min(GALLERY_MAX_SCALE, Math.max(GALLERY_MIN_SCALE, scale + amount))
        if (scale === GALLERY_MIN_SCALE) {
            x = 0
            y = 0
        }
        paintTransform()
    }
    const close = (): void => {
        document.documentElement.style.overflow = previousOverflow
        dialog.remove()
        opener.focus()
    }
    /* `closest`, not `:not([hidden])`: a thumbnail is reachable only when the
     * strip holding it is, and a single-image set hides the whole strip. */
    const focusable = (): HTMLElement[] =>
        Array.from(dialog.querySelectorAll<HTMLElement>('button, a[href]')).filter(
            (element) => !element.closest('[hidden]')
        )

    /*
     * The offer is a plain link into the same onboarding as every other
     * entry point, without carrying the displayed picture.
     */

    dialog.querySelector('[data-gallery-prev]')?.addEventListener('click', () => show(index - 1))
    dialog.querySelector('[data-gallery-next]')?.addEventListener('click', () => show(index + 1))
    dialog
        .querySelector('[data-gallery-zoom-in]')
        ?.addEventListener('click', () => zoom(GALLERY_SCALE_STEP))
    dialog
        .querySelector('[data-gallery-zoom-out]')
        ?.addEventListener('click', () => zoom(-GALLERY_SCALE_STEP))
    dialog.querySelector('[data-gallery-reset]')?.addEventListener('click', resetZoom)
    closeButton.addEventListener('click', close)
    /*
     * The ground around the picture closes. Every layer between the overlay
     * and the photograph is checked by identity rather than by `contains`, so
     * a press that lands on a control inside one of them is still that
     * control's - only the empty parts of the viewer are a way out.
     */
    const ground = [dialog, dialog.firstElementChild, view, stage]
    dialog.addEventListener('click', (event) => {
        if (ground.includes(event.target as Element)) close()
    })
    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close()
        if (event.key === 'ArrowLeft') show(index - 1)
        if (event.key === 'ArrowRight') show(index + 1)
        if (event.key === '+' || event.key === '=') zoom(GALLERY_SCALE_STEP)
        if (event.key === '-') zoom(-GALLERY_SCALE_STEP)
        if (event.key === '0') resetZoom()
        if (event.key === 'Tab') {
            const targets = focusable()
            const next = event.shiftKey ? targets.length - 1 : 0
            const atEdge = event.shiftKey
                ? document.activeElement === targets[0]
                : document.activeElement === targets[targets.length - 1]
            if (atEdge) {
                event.preventDefault()
                targets[next]?.focus()
            }
        }
    })
    stage.addEventListener('wheel', (event) => {
        event.preventDefault()
        zoom(event.deltaY < 0 ? GALLERY_SCALE_STEP : -GALLERY_SCALE_STEP)
    })
    stage.addEventListener('pointerdown', (event) => {
        touches.set(event.pointerId, event)
        stage.setPointerCapture?.(event.pointerId)
        dragging = scale > 1
        pointerX = event.clientX
        pointerY = event.clientY
        swipeX = event.clientX
        swipeY = event.clientY
    })
    stage.addEventListener('pointermove', (event) => {
        if (touches.has(event.pointerId)) touches.set(event.pointerId, event)
        const active = Array.from(touches.values())
        if (active.length === 2) {
            const distance = Math.hypot(
                active[0].clientX - active[1].clientX,
                active[0].clientY - active[1].clientY
            )
            if (pinchDistance > 0) zoom((distance - pinchDistance) / 150)
            pinchDistance = distance
        } else if (dragging) {
            x += event.clientX - pointerX
            y += event.clientY - pointerY
            pointerX = event.clientX
            pointerY = event.clientY
            paintTransform()
        }
    })
    const release = (event: PointerEvent): void => {
        /*
         * A flick across an image nobody has zoomed into is how you turn the
         * page on a phone. Zoomed in the same gesture is panning, and one
         * finger of a pinch is neither.
         */
        const travelled = event.clientX - swipeX

        if (
            touches.size === 1 &&
            scale === GALLERY_MIN_SCALE &&
            Math.abs(travelled) > GALLERY_SWIPE_PX &&
            Math.abs(travelled) > Math.abs(event.clientY - swipeY)
        ) {
            show(travelled < 0 ? index + 1 : index - 1)
        }

        touches.delete(event.pointerId)
        dragging = false
        pinchDistance = 0
    }
    stage.addEventListener('pointerup', release)
    stage.addEventListener('pointercancel', release)

    show(initialIndex)
    closeButton.focus()
}

/**
 * The strip of every other picture in the article, along the bottom. The
 * arrows say there is more; this says what it is, which is the difference
 * between paging blindly and picking the haircut you came back for.
 */
function buildGalleryStrip(
    strip: HTMLElement,
    items: GalleryItem[],
    onPick: (index: number) => void
): HTMLButtonElement[] {
    return items.map((item, index) => {
        const thumb = document.createElement('button')
        thumb.type = 'button'
        thumb.className = 'gallery-lightbox-thumb'
        thumb.setAttribute('aria-label', `Show image ${index + 1}: ${item.caption || item.alt}`)

        const picture = document.createElement('img')
        picture.src = item.src
        picture.alt = ''
        picture.loading = 'lazy'
        picture.decoding = 'async'
        thumb.appendChild(picture)

        if (item.tryUrl !== null) {
            const flag = document.createElement('span')
            flag.className = 'gallery-lightbox-thumb-flag'
            flag.setAttribute('aria-hidden', 'true')
            flag.textContent = 'Try'
            thumb.appendChild(flag)
        }

        thumb.addEventListener('click', () => onPick(index))
        strip.appendChild(thumb)

        return thumb
    })
}

export function initYoutubeFacades(): void {
    document.querySelectorAll<HTMLElement>('[data-youtube-id]').forEach((facade) => {
        const button = facade.querySelector<HTMLButtonElement>('button')
        const videoId = facade.dataset.youtubeId

        if (!button || !videoId) return

        button.addEventListener(
            'click',
            () => {
                const iframe = document.createElement('iframe')
                iframe.src = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(videoId)}?autoplay=1`
                iframe.title = button.getAttribute('aria-label') ?? 'YouTube video'
                iframe.allow =
                    'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture'
                iframe.allowFullscreen = true
                iframe.loading = 'lazy'
                facade.replaceChildren(iframe)
            },
            { once: true }
        )
    })
}


const OUTLINE_SIDEBAR_QUERY = '(min-width: 800px)'
const OUTLINE_ACTIVE_OFFSET_PX = 140
const OUTLINE_FADE = '1.75rem'
export function initArticleOutline(): void {
    const outline = document.querySelector<HTMLDetailsElement>('[data-article-outline]')

    if (!outline || !bindOnce(outline, 'ArticleOutline')) {
        return
    }

    /* The list scrolls, not the card around it: the label stays put. */
    const list = outline.querySelector<HTMLElement>('nav') ?? outline
    const links = Array.from(outline.querySelectorAll<HTMLAnchorElement>('[data-outline-link]'))
    const targets = links
        .map((link) => ({ link, heading: document.getElementById(link.dataset.outlineLink ?? '') }))
        .filter(
            (entry): entry is { link: HTMLAnchorElement; heading: HTMLElement } =>
                entry.heading !== null
        )

    if (targets.length === 0) {
        return
    }

    /* The numbered sections, and the readout in the label that counts them. A
       sub-heading belongs to the section that contains it: a reader inside one
       is still in section three, not somewhere between three and four. */
    const sections = Array.from(list.querySelector('ol')?.children ?? []).filter(
        (child): child is HTMLElement => child.classList.contains('outline-branch')
    )
    const readout = outline.querySelector<HTMLElement>('[data-outline-current]')
    const digits = readout?.textContent?.trim().length ?? 2

    const sectionNumber = (link: HTMLElement): number =>
        sections.findIndex((section) => section.contains(link)) + 1

    const wide =
        typeof window.matchMedia === 'function' ? window.matchMedia(OUTLINE_SIDEBAR_QUERY) : null

    const applyViewport = (): void => {
        outline.open = wide?.matches ?? true
    }

    applyViewport()
    wide?.addEventListener('change', applyViewport)

    /* Beside the article there is nothing to collapse into, so the summary is
       a label rather than a control there. It is click-proofed in CSS; this
       covers the keyboard, which pointer-events cannot. */
    outline.addEventListener('toggle', () => {
        if (!outline.open && (wide?.matches ?? true)) {
            outline.open = true
        }

        /* A closed list measures zero, so the edges are only worth asking
           about once it has been opened again. */
        fade(list)
    })

    list.addEventListener('scroll', () => fade(list), { passive: true })

    /* The reader is "in" the last heading that has passed the trigger line,
       and in the first one until any of them has - a post always opens on its
       own first section rather than on nothing. */
    const currentIndex = (): number => {
        let index = 0

        targets.forEach((target, position) => {
            if (target.heading.getBoundingClientRect().top <= OUTLINE_ACTIVE_OFFSET_PX) {
                index = position
            }
        })

        /* The last section can be too short to ever reach the trigger line, so
           the bottom of a scrollable page claims it. */
        const height = document.documentElement.scrollHeight

        if (height > window.innerHeight && window.scrollY + window.innerHeight >= height - 2) {
            index = targets.length - 1
        }

        return index
    }

    let marked = -1

    const mark = (): void => {
        const index = currentIndex()

        if (index === marked) {
            return
        }

        marked = index

        targets.forEach((target, position) => {
            if (position === index) {
                target.link.setAttribute('aria-current', 'true')
            } else {
                target.link.removeAttribute('aria-current')
            }

            /* The rail is the progress bar: copper behind what has been read. */
            const branch = target.link.closest<HTMLElement>('.outline-branch')

            if (branch) {
                branch.dataset.outlineState =
                    position === index ? 'current' : position < index ? 'read' : 'unread'
            }
        })

        const section = sectionNumber(targets[index].link)

        if (readout && section > 0) {
            readout.textContent = String(section).padStart(digits, '0')
        }

        keepVisible(list, targets[index].link)
        fade(list)
    }

    let queued = false

    const onScroll = (): void => {
        if (queued) {
            return
        }

        queued = true
        window.requestAnimationFrame(() => {
            queued = false
            mark()
        })
    }

    window.addEventListener('scroll', onScroll, { passive: true })
    window.addEventListener('resize', onScroll, { passive: true })
    mark()

    links.forEach((link) => {
        link.addEventListener('click', () => {
            /* On a phone the list covers the article it is a map of. */
            if (!(wide?.matches ?? true)) {
                outline.open = false
            }

        })
    })
}

/**
 * Fade whichever edge of the outline has list hidden behind it.
 *
 * The height is capped in CSS, so the only honest way to say "there is more
 * below" is to let the last row dissolve. A list that fits gets no fade at
 * all, because a fade over a complete list only reads as a clipped one.
 */
function fade(list: HTMLElement): void {
    const overflow = list.scrollHeight - list.clientHeight
    const scrollable = overflow > 1

    list.style.setProperty(
        '--outline-fade-top',
        scrollable && list.scrollTop > 1 ? OUTLINE_FADE : '0px'
    )
    list.style.setProperty(
        '--outline-fade-bottom',
        scrollable && list.scrollTop < overflow - 1 ? OUTLINE_FADE : '0px'
    )
}

/**
 * Scroll a long outline just enough to show the current entry. Deliberately
 * not scrollIntoView: that would also scroll the page the reader is moving
 * through, fighting them for the scroll position.
 */
function keepVisible(container: HTMLElement, link: HTMLElement): void {
    if (container.scrollHeight <= container.clientHeight) {
        return
    }

    const box = link.getBoundingClientRect()
    const offset = box.top - container.getBoundingClientRect().top + container.scrollTop

    if (offset < container.scrollTop) {
        container.scrollTop = offset
    } else if (offset + box.height > container.scrollTop + container.clientHeight) {
        container.scrollTop = offset + box.height - container.clientHeight
    }
}
