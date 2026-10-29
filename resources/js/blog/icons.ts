/**
 * The marks the panels draw.
 *
 * The toolbar's own icons are printed by x-blog.editor-icon, because the bar is
 * server-rendered and has to be legible with the bundle still in flight. These
 * are the ones only the panels need - the seventeen things the Insert list can
 * drop into an article, and the cross that closes a panel - so they live here
 * rather than being shipped down with the bootstrap.
 *
 * Material's 24x24 marks, filled with currentColor, same as the Blade set.
 */

export type IconName =
    | 'close'
    | 'paragraph'
    | 'heading'
    | 'subheading'
    | 'bullets'
    | 'numbers'
    | 'quote'
    | 'code'
    | 'divider'
    | 'image'
    | 'anchor'
    | 'brief'
    | 'steps'
    | 'gallery'
    | 'face'
    | 'video'
    | 'ratio'
    | 'link'
    | 'clear'

const MARKS: Record<IconName, string> = {
    close: 'M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41Z',
    paragraph: 'M15 15H3v2h12v-2Zm0-8H3v2h12V7ZM3 13h18v-2H3v2Zm0 8h18v-2H3v2ZM3 3v2h18V3H3Z',
    heading: 'M5 4v3h5.5v12h3V7H19V4H5Z',
    subheading: 'M2.5 4v3h5v12h3V7h5V4h-13Zm19 5h-9v3h3v7h3v-7h3V9Z',
    bullets:
        'M4 10.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Zm0-6a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Zm0 12a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3ZM7 19h14v-2H7v2Zm0-6h14v-2H7v2Zm0-8v2h14V5H7Z',
    numbers:
        'M2 17h2v.5H3v1h1v.5H2v1h3v-4H2v1Zm1-9h1V4H2v1h1v3Zm-1 3h1.8L2 13.1v.9h3v-1H3.2L5 10.9V10H2v1Zm5-6v2h14V5H7Zm0 14h14v-2H7v2Zm0-6h14v-2H7v2Z',
    quote: 'M6 17h3l2-4V7H5v6h3l-2 4Zm8 0h3l2-4V7h-6v6h3l-2 4Z',
    code: 'M9.4 16.6 4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4Zm5.2 0 4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4Z',
    divider: 'M4 11h16v2H4v-2Z',
    image: 'M21 19V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2ZM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5Z',
    anchor: 'M17 3H7a2 2 0 0 0-2 2v16l7-3 7 3V5a2 2 0 0 0-2-2Z',
    brief: 'M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2Zm-3 9H7V9h10v2Zm-4 4H7v-2h6v2Zm4-8H7V5h10v2Z',
    steps: 'M22 7h-9v2h9V7Zm0 8h-9v2h9v-2ZM5.54 11 2 7.46l1.41-1.41 2.12 2.12 4.24-4.24 1.41 1.41L5.54 11Zm0 8L2 15.46l1.41-1.41 2.12 2.12 4.24-4.24 1.41 1.41L5.54 19Z',
    gallery:
        'M22 16V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2Zm-11-4 2.03 2.71L16 11l4 5H8l3-4ZM2 6v14a2 2 0 0 0 2 2h14v-2H4V6H2Z',
    face: 'M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-3.34 0-10 1.67-10 5v3h20v-3c0-3.33-6.66-5-10-5Z',
    video: 'M21.58 7.19a2.5 2.5 0 0 0-1.76-1.77C18.25 5 12 5 12 5s-6.25 0-7.82.42a2.5 2.5 0 0 0-1.76 1.77C2 8.77 2 12 2 12s0 3.23.42 4.81a2.5 2.5 0 0 0 1.76 1.77C5.75 19 12 19 12 19s6.25 0 7.82-.42a2.5 2.5 0 0 0 1.76-1.77C22 15.23 22 12 22 12s0-3.23-.42-4.81ZM10 15.5v-7l6 3.5-6 3.5Z',
    ratio: 'M19 12h-2v3h-3v2h5v-5ZM7 9h3V7H5v5h2V9Zm14-6H3a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h18a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2Zm0 16.01H3V4.99h18v14.02Z',
    link: 'M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1ZM8 13h8v-2H8v2Zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5Z',
    clear: 'M3.27 5 2 6.27l6.97 6.97L6.5 19h3l1.57-3.66L16.73 21 18 19.73 3.55 5.27 3.27 5ZM6 5v.18L8.82 8h2.4l-.72 1.68 2.1 2.1L14.21 8H20V5H6Z',
}

/** One mark, sized by the CSS on `.editor-icon` and colored by its parent. */
export function icon(name: IconName): SVGSVGElement {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg')
    svg.setAttribute('class', 'editor-icon')
    svg.setAttribute('viewBox', '0 0 24 24')
    svg.setAttribute('fill', 'currentColor')
    svg.setAttribute('aria-hidden', 'true')
    svg.setAttribute('focusable', 'false')

    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path')
    path.setAttribute('d', MARKS[name])
    svg.appendChild(path)

    return svg
}
