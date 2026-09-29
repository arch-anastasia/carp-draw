/**
 * happy-dom backs `sessionStorage` with a Proxy whose `set` trap writes storage
 * items rather than object properties, so `vi.spyOn(sessionStorage, 'getItem')`
 * silently installs nothing: the call still reaches the real implementation and
 * the blocked-storage branch is never taken. The functions under test accept a
 * `Storage` argument for exactly this reason - hand them a stub that throws
 * where a browser with site data blocked would.
 */
export function blockedStorage(method: 'getItem' | 'setItem'): Storage {
    const blocked = (): never => {
        throw new Error('Storage blocked')
    }

    return {
        length: 0,
        clear: () => undefined,
        key: () => null,
        removeItem: () => undefined,
        getItem: method === 'getItem' ? blocked : () => null,
        setItem: method === 'setItem' ? blocked : () => undefined,
    }
}
