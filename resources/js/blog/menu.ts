/**
 * The menu that opens where the pointer is.
 *
 * Used for the right-click on a picture and for the toolbar's own insert list,
 * so it is written as a list of items rather than as anything image-shaped. One
 * menu exists at a time; opening another closes the first.
 */

export interface MenuItem {
    label: string
    onSelect?: () => void
    /** A heading rather than a choice. */
    heading?: boolean
    /** A rule above this item. */
    separator?: boolean
    /** Drawn with a tick - for the alignment a picture already has. */
    checked?: boolean
    disabled?: boolean
    destructive?: boolean
}

let open: HTMLElement | null = null

export function closeMenu(): void {
    open?.remove()
    open = null
}

/**
 * Put `items` on screen at the given viewport point, nudged back inside it if
 * the menu would hang off an edge.
 */
export function openMenu(x: number, y: number, items: MenuItem[]): void {
    closeMenu()

    const menu = document.createElement('div')
    menu.className = 'blog-image-menu'
    menu.setAttribute('role', 'menu')

    for (const item of items) {
        if (item.separator) {
            menu.appendChild(document.createElement('hr'))
        }

        if (item.heading) {
            const heading = document.createElement('p')
            heading.className = 'menu-heading'
            heading.textContent = item.label
            menu.appendChild(heading)

            continue
        }

        const button = document.createElement('button')
        button.type = 'button'
        button.setAttribute('role', 'menuitem')
        button.textContent = item.label
        button.disabled = item.disabled ?? false

        if (item.checked !== undefined) {
            button.setAttribute('aria-checked', item.checked ? 'true' : 'false')
        }

        if (item.destructive) {
            button.dataset.destructive = 'true'
        }

        button.addEventListener('click', () => {
            closeMenu()
            item.onSelect?.()
        })

        menu.appendChild(button)
    }

    /*
     * Positioned in page coordinates, not viewport ones: the menu is appended
     * to <body> and the page scrolls under it.
     */
    menu.style.left = `${x + window.scrollX}px`
    menu.style.top = `${y + window.scrollY}px`
    menu.style.visibility = 'hidden'
    document.body.appendChild(menu)

    const bounds = menu.getBoundingClientRect()

    if (bounds.right > window.innerWidth - 8) {
        menu.style.left = `${window.scrollX + Math.max(8, window.innerWidth - bounds.width - 8)}px`
    }

    if (bounds.bottom > window.innerHeight - 8) {
        menu.style.top = `${window.scrollY + Math.max(8, y - bounds.height)}px`
    }

    menu.style.visibility = 'visible'
    open = menu

    const dismiss = (event: Event): void => {
        if (event.target instanceof Node && menu.contains(event.target)) {
            return
        }

        closeMenu()
        detach()
    }

    const onKeydown = (event: KeyboardEvent): void => {
        if (event.key === 'Escape') {
            closeMenu()
            detach()
        }
    }

    function detach(): void {
        document.removeEventListener('pointerdown', dismiss, true)
        document.removeEventListener('keydown', onKeydown, true)
        window.removeEventListener('resize', closeMenu)
    }

    document.addEventListener('pointerdown', dismiss, true)
    document.addEventListener('keydown', onKeydown, true)
    window.addEventListener('resize', closeMenu, { once: true })
}
