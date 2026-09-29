import { isSentryReady, reportMessage } from '@/plugins/errorReporter'
import type { AssetFailureContext } from '@/plugins/errorReporter'

const PRELOAD_RELOAD_ATTEMPTED = 'vite_preload_reload_attempted'
let reloadRequested = false

type PreloadFailureReporter = (failedUrl?: string) => void

/**
 * A tab left open across a deploy can request a chunk from the previous build.
 * Vite makes this event cancelable, so handling it here prevents the expected
 * 404 from becoming an unhandled error after the single recovery reload.
 */
export function handleVitePreloadError(
    event: Event,
    storage?: Storage,
    reload: () => void = reloadPage,
    reportFailure: PreloadFailureReporter = reportRepeatedPreloadFailure,
): void {
    event.preventDefault()

    if (!tryRecoveryReload(storage, reload)) {
        try {
            reportFailure(getFailedUrl(event))
        } catch {
            // A blocked reporting SDK must not turn a handled chunk failure
            // back into an uncaught error.
        }
    }
}

/** Element failures do not emit vite:preloadError, but spend the same reload. */
export function handleAssetLoadFailure(
    error: Error,
    context: AssetFailureContext,
    storage?: Storage,
    reload: () => void = reloadPage,
): boolean {
    let asset: URL

    try {
        asset = new URL(context.asset_url, window.location.href)
    } catch {
        return false
    }

    if (asset.origin !== window.location.origin) {
        reportMessage(error.message, 'third_party_asset_blocked', {
            ...context,
            host: asset.host,
        })

        return true
    }

    return asset.pathname.startsWith('/build/') && tryRecoveryReload(storage, reload)
}

function tryRecoveryReload(storage: Storage | undefined, reload: () => void): boolean {
    try {
        // Even reading window.sessionStorage can throw when site data is blocked.
        const guard = storage ?? window.sessionStorage

        if (guard.getItem(PRELOAD_RELOAD_ATTEMPTED) !== null) {
            return false
        }

        guard.setItem(PRELOAD_RELOAD_ATTEMPTED, '1')
    } catch {
        // Without a persisted guard, a reload could repeat forever.
        return false
    }

    reload()

    return true
}

function reloadPage(): void {
    reloadRequested = true
    window.location.reload()
}

/**
 * Clear only after the replacement bundle mounts. Clearing during module
 * evaluation would allow a broken deploy to reload forever.
 */
export function clearVitePreloadReloadAttempt(storage?: Storage): void {
    // Init can finish while navigation is pending. Only the replacement
    // document may clear the guard, never the document that spent it.
    if (reloadRequested) {
        return
    }

    try {
        (storage ?? window.sessionStorage).removeItem(PRELOAD_RELOAD_ATTEMPTED)
    } catch {
        // Unavailable site data must not turn a successful init into a failure.
    }
}

export function registerVitePreloadErrorHandler(): void {
    window.addEventListener('vite:preloadError', handleVitePreloadError)
}

function reportRepeatedPreloadFailure(failedUrl?: string): void {
    if (!isSentryReady()) {
        return
    }

    reportMessage('Vite preload failed after recovery reload', 'chunk_load_failure', {
        ...(failedUrl ? { url: failedUrl } : {}),
    })
}

function getFailedUrl(event: Event): string | undefined {
    const payload = (event as Event & { payload?: unknown }).payload

    if (typeof payload === 'object' && payload !== null && 'url' in payload) {
        const url = (payload as { url?: unknown }).url

        if (typeof url === 'string') {
            return url
        }
    }

    const message = payload instanceof Error ? payload.message : String(payload ?? '')

    return message.match(/(?:https?:\/\/|\/)[^\s)]+/)?.[0]
}
