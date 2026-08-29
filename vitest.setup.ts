/**
 * Node 22+ ships an experimental global `localStorage` (gated behind
 * `--localstorage-file`) that implements only part of the Web Storage API and
 * shadows happy-dom's implementation. Install a complete in-memory shim when
 * the active storage is missing or incomplete so component tests that touch
 * localStorage / sessionStorage (consent, analytics) run consistently.
 */
class MemoryStorage implements Storage {
    private store = new Map<string, string>()

    get length(): number {
        return this.store.size
    }

    clear(): void {
        this.store.clear()
    }

    getItem(key: string): string | null {
        return this.store.has(key) ? (this.store.get(key) as string) : null
    }

    key(index: number): string | null {
        return Array.from(this.store.keys())[index] ?? null
    }

    removeItem(key: string): void {
        this.store.delete(key)
    }

    setItem(key: string, value: string): void {
        this.store.set(key, String(value))
    }
}

function ensureStorage(name: 'localStorage' | 'sessionStorage'): void {
    const current = (globalThis as Record<string, unknown>)[name] as Storage | undefined

    if (!current || typeof current.removeItem !== 'function') {
        Object.defineProperty(globalThis, name, {
            value: new MemoryStorage(),
            writable: true,
            configurable: true,
        })
    }
}

ensureStorage('localStorage')
ensureStorage('sessionStorage')
