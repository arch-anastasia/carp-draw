/**
 * Cropping a picture that is already in an article.
 *
 * The crop happens in the browser, on a canvas, at the file's own resolution -
 * so a 1440px cover cropped to its middle third is still 480 real pixels wide,
 * not a scaled-up copy of what fitted on screen. What comes out is webp bytes
 * the editor uploads like any other picture.
 *
 * A crop is therefore never destructive. The upload is stored under its own
 * content hash, so the original file keeps its URL and every article still
 * pointing at it is untouched - which is the same promise the blog's image
 * library makes everywhere else.
 */

import { openDialog } from './dialog'

/** Where the crop is, in the natural pixels of the source image. */
interface CropRect {
    x: number
    y: number
    width: number
    height: number
}

const RATIOS: Array<{ label: string; value: number | null }> = [
    { label: 'Free', value: null },
    { label: '3:2', value: 3 / 2 },
    { label: '16:9', value: 16 / 9 },
    { label: '1:1', value: 1 },
    { label: '4:5', value: 4 / 5 },
]

/** How small a crop is allowed to get, on screen, before it stops shrinking. */
const MINIMUM_FRAME_PX = 24

/**
 * Crop `source` and resolve with the bytes, or with null if the writer
 * dismissed the window.
 *
 * Resolves null too when the picture cannot be read into a canvas at all - a
 * cross-origin file served without CORS headers taints it, and the browser
 * refuses to hand the pixels back. The caller reports that; there is nothing
 * this can do about it from here.
 */
export async function cropImage(source: string, fileName: string): Promise<Blob | null> {
    const image = await loadImage(source)

    if (image === null) {
        return null
    }

    const stage = document.createElement('div')
    stage.className = 'blog-crop-stage'

    const preview = document.createElement('img')
    preview.src = image.src
    preview.alt = ''
    stage.appendChild(preview)

    const shade = document.createElement('div')
    shade.className = 'blog-crop-shade'
    stage.appendChild(shade)

    const frame = document.createElement('div')
    frame.className = 'blog-crop-frame'

    for (const handle of ['nw', 'ne', 'sw', 'se']) {
        const grip = document.createElement('span')
        grip.dataset.handle = handle
        frame.appendChild(grip)
    }

    stage.appendChild(frame)

    const body = document.createElement('div')

    const ratios = document.createElement('div')
    ratios.className = 'dialog-ratios'
    body.appendChild(ratios)
    body.appendChild(stage)

    /* Screen pixels, relative to the displayed image, until the very last step. */
    let rect = { x: 0, y: 0, width: 0, height: 0 }
    let ratio: number | null = null

    const draw = (): void => {
        frame.style.left = `${rect.x}px`
        frame.style.top = `${rect.y}px`
        frame.style.width = `${rect.width}px`
        frame.style.height = `${rect.height}px`

        const bounds = preview.getBoundingClientRect()
        const stageBounds = stage.getBoundingClientRect()
        const left = bounds.left - stageBounds.left
        const top = bounds.top - stageBounds.top

        /*
         * The dimmed surround is one element with a hole cut in it, rather than
         * four strips to keep in step with each other.
         */
        shade.style.setProperty(
            '--crop-shade',
            `polygon(0 0, 100% 0, 100% 100%, 0 100%, 0 0,
                ${left + rect.x}px ${top + rect.y}px,
                ${left + rect.x}px ${top + rect.y + rect.height}px,
                ${left + rect.x + rect.width}px ${top + rect.y + rect.height}px,
                ${left + rect.x + rect.width}px ${top + rect.y}px,
                ${left + rect.x}px ${top + rect.y}px)`
        )
    }

    const reset = (): void => {
        const bounds = preview.getBoundingClientRect()
        let width = bounds.width
        let height = bounds.height

        if (ratio !== null) {
            if (width / height > ratio) {
                width = height * ratio
            } else {
                height = width / ratio
            }
        }

        const stageBounds = stage.getBoundingClientRect()

        rect = {
            x: bounds.left - stageBounds.left + (bounds.width - width) / 2,
            y: bounds.top - stageBounds.top + (bounds.height - height) / 2,
            width,
            height,
        }

        draw()
    }

    for (const option of RATIOS) {
        const button = document.createElement('button')
        button.type = 'button'
        button.className = 'dialog-button'
        button.textContent = option.label
        button.setAttribute('aria-pressed', option.value === null ? 'true' : 'false')

        button.addEventListener('click', () => {
            ratio = option.value

            for (const sibling of Array.from(ratios.children)) {
                sibling.setAttribute('aria-pressed', sibling === button ? 'true' : 'false')
            }

            reset()
        })

        ratios.appendChild(button)
    }

    dragging(stage, preview, frame, {
        get rect() {
            return rect
        },
        set rect(next: CropRect) {
            rect = next
        },
        get ratio() {
            return ratio
        },
        draw,
    })

    const answer = await openDialog({
        title: 'Crop image',
        body,
        confirmLabel: 'Apply crop',
        onOpen: () => {
            /*
             * The frame can only be placed once the image has been laid out,
             * and `complete` is already true here - the element was decoded
             * before the dialog opened - so one frame is enough.
             */
            requestAnimationFrame(reset)
        },
    })

    if (answer === null) {
        return null
    }

    return toBlob(image, preview, stage, rect, fileName)
}

/** The crop rectangle in the file's own pixels, drawn onto a canvas. */
function toBlob(
    image: HTMLImageElement,
    preview: HTMLImageElement,
    stage: HTMLElement,
    rect: CropRect,
    fileName: string
): Promise<Blob | null> {
    const bounds = preview.getBoundingClientRect()
    const stageBounds = stage.getBoundingClientRect()
    const scale = image.naturalWidth / bounds.width

    const x = Math.max(0, Math.round((rect.x - (bounds.left - stageBounds.left)) * scale))
    const y = Math.max(0, Math.round((rect.y - (bounds.top - stageBounds.top)) * scale))
    const width = Math.min(image.naturalWidth - x, Math.round(rect.width * scale))
    const height = Math.min(image.naturalHeight - y, Math.round(rect.height * scale))

    if (width < 1 || height < 1) {
        return Promise.resolve(null)
    }

    const canvas = document.createElement('canvas')
    canvas.width = width
    canvas.height = height

    const context = canvas.getContext('2d')

    if (context === null) {
        return Promise.resolve(null)
    }

    context.drawImage(image, x, y, width, height, 0, 0, width, height)

    return new Promise((resolve) => {
        try {
            canvas.toBlob(
                (blob) => resolve(blob === null ? null : named(blob, fileName)),
                'image/webp',
                0.9
            )
        } catch {
            /* A tainted canvas: the picture is not ours to read back. */
            resolve(null)
        }
    })
}

function named(blob: Blob, fileName: string): Blob {
    return new File([blob], `${fileName}.webp`, { type: 'image/webp' })
}

/** Moving the frame, and dragging its corners. */
function dragging(
    stage: HTMLElement,
    preview: HTMLImageElement,
    frame: HTMLElement,
    state: { rect: CropRect; ratio: number | null; draw: () => void }
): void {
    let handle: string | null = null
    let origin = { x: 0, y: 0 }
    let start: CropRect = { x: 0, y: 0, width: 0, height: 0 }

    const limits = (): { left: number; top: number; right: number; bottom: number } => {
        const bounds = preview.getBoundingClientRect()
        const stageBounds = stage.getBoundingClientRect()
        const left = bounds.left - stageBounds.left
        const top = bounds.top - stageBounds.top

        return { left, top, right: left + bounds.width, bottom: top + bounds.height }
    }

    const onDown = (event: PointerEvent): void => {
        const target = event.target as HTMLElement

        if (!frame.contains(target)) {
            return
        }

        event.preventDefault()
        handle = target.dataset.handle ?? 'move'
        origin = { x: event.clientX, y: event.clientY }
        start = { ...state.rect }
        stage.setPointerCapture(event.pointerId)
    }

    const onMove = (event: PointerEvent): void => {
        if (handle === null) {
            return
        }

        event.preventDefault()

        const edges = limits()
        const dx = event.clientX - origin.x
        const dy = event.clientY - origin.y

        if (handle === 'move') {
            state.rect = {
                ...start,
                x: clamp(start.x + dx, edges.left, edges.right - start.width),
                y: clamp(start.y + dy, edges.top, edges.bottom - start.height),
            }
            state.draw()

            return
        }

        const east = handle.includes('e')
        const south = handle.includes('s')

        let width = east ? start.width + dx : start.width - dx
        let height = south ? start.height + dy : start.height - dy

        width = Math.max(MINIMUM_FRAME_PX, width)
        height = Math.max(MINIMUM_FRAME_PX, height)

        if (state.ratio !== null) {
            height = width / state.ratio
        }

        const x = east ? start.x : start.x + start.width - width
        const y = south ? start.y : start.y + start.height - height

        /* Refuse a drag that would leave the picture rather than clipping it. */
        if (
            x < edges.left ||
            y < edges.top ||
            x + width > edges.right ||
            y + height > edges.bottom
        ) {
            return
        }

        state.rect = { x, y, width, height }
        state.draw()
    }

    const onUp = (event: PointerEvent): void => {
        handle = null

        if (stage.hasPointerCapture(event.pointerId)) {
            stage.releasePointerCapture(event.pointerId)
        }
    }

    stage.addEventListener('pointerdown', onDown)
    stage.addEventListener('pointermove', onMove)
    stage.addEventListener('pointerup', onUp)
    stage.addEventListener('pointercancel', onUp)
}

function clamp(value: number, lowest: number, highest: number): number {
    return Math.min(Math.max(value, lowest), Math.max(lowest, highest))
}

/**
 * The picture, decoded and readable.
 *
 * `crossOrigin` is set before the src so a file on another host can be read
 * back off the canvas when that host allows it; without it the canvas is
 * tainted and `toBlob` throws. Every image the blog stores is same-origin, so
 * this only matters for a picture somebody pasted in from elsewhere.
 */
function loadImage(source: string): Promise<HTMLImageElement | null> {
    return new Promise((resolve) => {
        const image = new Image()

        if (!source.startsWith('/') && !source.startsWith(window.location.origin)) {
            image.crossOrigin = 'anonymous'
        }

        image.addEventListener('load', () => resolve(image), { once: true })
        image.addEventListener('error', () => resolve(null), { once: true })
        image.src = source
    })
}
