import type { AnalyticsUser } from '@/types/analytics'

/**
 * The browser half of the error pipeline, and a deliberate mirror of
 * app/Services/ErrorReporter.php: one function that every failure goes
 * through, fanning out to Sentry and to PostHog error tracking so an issue
 * can be read either as a stack trace or as something that happened to a
 * person mid-funnel.
 *
 * ## Why neither SDK is imported here
 *
 * This module imports nothing but a type. Both destinations are handed to it
 * at runtime - `registerSentry()` and `registerExceptionSink()` - and the
 * reason is bundle weight on the pages that can least afford it.
 *
 * Sentry is 90KB gzipped and posthog-js another 58KB, against a marketing
 * bundle of 14KB. A static import of either would put it in the critical path
 * of every landing page, which means paying for observability in the one
 * metric this funnel is measured on. Instead: the Inertia bundle registers
 * both eagerly, and the Blade bundle registers the PostHog instance the page
 * already has as a global and fetches Sentry after paint.
 *
 * Until a destination registers, reporting still works. Errors raised in the
 * gap are buffered per destination and replayed the moment it arrives, so the
 * earliest failures - the ones worth the most - are not the ones lost.
 *
 * ## Consent
 *
 * Crash reporting is NOT gated on the analytics consent banner, on purpose.
 * Consent here governs analytics and marketing; keeping a service working is
 * a separate basis, and gating it would mean the visitors most likely to hit
 * a broken page - the privacy-conscious ones who declined - are the ones we
 * can never hear about.
 *
 * What makes that defensible is what is NOT sent: no session replay (the
 * studio's DOM holds the visitor's photo as a data URI), no cookies, no IP,
 * no request bodies. An authenticated user's id is attached because they have
 * an account with us and "which accounts are affected" is the first support
 * question; a signed-out visitor stays anonymous.
 *
 * PostHog is the other half and IS consent-gated, because it is PostHog - if
 * the visitor opted out, the capture is dropped on the floor. Sentry still
 * gets it. Neither destination may be the other's single point of failure.
 */

/** The slice of the Sentry SDK this module needs. Kept structural so that
 *  importing the SDK's types here does not pull in the SDK itself. */
export interface SentryApi {
    captureException(error: Error, hint?: Record<string, unknown>): unknown
    captureMessage(message: string, context?: Record<string, unknown>): unknown
    setUser(user: { id: string } | null): unknown
}

/**
 * Where a `$exception` event goes. Supplied by the entry point so this module
 * never has to import posthog-js. Mirrors `posthog.captureException()`.
 */
export type ExceptionSink = (error: Error, properties: Record<string, unknown>) => void

export interface AssetFailureContext extends Record<string, unknown> {
    subsystem: string
    tag: string
    url: string
    asset_url: string
}

type AssetFailureHandler = (error: Error, context: AssetFailureContext) => boolean

/** Errors raised before a destination finished loading, replayed once it has. */
interface BufferedError {
    error: Error
    code: string
    context: Record<string, unknown>
}

/**
 * Small on purpose. If a page throws more than this before the SDK arrives it
 * is comprehensively broken, and the first few are enough to say so.
 */
const MAX_BUFFERED_ERRORS = 10

let sentry: SentryApi | null = null
let sink: ExceptionSink | null = null
let pendingUserId: string | null = null
let handlersInstalled = false
let debugMode = false
let assetFailureHandler: AssetFailureHandler | null = null

/** Recovery is supplied by the entry point, keeping this reporter dependency-free. */
export function registerAssetFailureHandler(handler: AssetFailureHandler): void {
    assetFailureHandler = handler
}

/*
 * One queue per destination, because they arrive at different times and an
 * error must reach each exactly once. PostHog is usually already up when the
 * first error lands; Sentry, on a marketing page, is a chunk still in flight.
 */
const bufferedForSentry: BufferedError[] = []
const bufferedForSink: BufferedError[] = []

/**
 * Install the global handlers and remember whether to talk.
 *
 * Safe to call before - or without - Sentry: this is what makes PostHog
 * reporting work in every environment that has no DSN, which is all of them
 * except production.
 */
export function initErrorReporting(debug = false): void {
    debugMode = debug

    if (handlersInstalled) {
        return
    }

    handlersInstalled = true
    installGlobalHandlers()
}

/**
 * Hand the loaded SDK over. Called by plugins/sentry.ts once `Sentry.init()`
 * has returned; anything reported in the meantime is replayed here.
 */
export function registerSentry(api: SentryApi): void {
    sentry = api

    if (pendingUserId !== null) {
        identifyForErrors({ distinctId: pendingUserId } as AnalyticsUser)
    }

    for (const entry of bufferedForSentry.splice(0, bufferedForSentry.length)) {
        sendToSentry(entry.error, entry.code, entry.context)
    }
}

/**
 * Hand over the PostHog instance to report `$exception` events through.
 *
 * The entry point supplies it: `getPostHog()` in the Inertia bundle, the
 * `window.posthog` global the Blade snippet installs on marketing pages.
 */
export function registerExceptionSink(exceptionSink: ExceptionSink): void {
    sink = exceptionSink

    for (const entry of bufferedForSink.splice(0, bufferedForSink.length)) {
        sendToSink(entry.error, entry.code, entry.context)
    }
}

/**
 * Attach the signed-in user's id, and nothing else.
 *
 * Called from the same place PostHog is identified, so the two agree on who
 * the person is and an issue in Sentry can be looked up in the funnel. Held
 * until the SDK loads rather than dropped - on a lazy page the user is known
 * long before Sentry is.
 */
export function identifyForErrors(user: AnalyticsUser): void {
    pendingUserId = user.distinctId

    try {
        sentry?.setUser({ id: user.distinctId })
    } catch {
        // An unattributed error still beats a thrown one inside the reporter.
    }
}

/** Clear the user on sign-out, so the next person's errors are not theirs. */
export function resetErrorIdentity(): void {
    pendingUserId = null

    try {
        sentry?.setUser(null)
    } catch {
        // As above.
    }
}

/**
 * Report one failure to both destinations.
 *
 * `code` is the stable name the dashboards group on ('render_poll_failed',
 * 'reference_upload_failed'). Keep it free of ids and counts - anything
 * variable belongs in `context`.
 */
export function reportError(
    error: unknown,
    code: string,
    context: Record<string, unknown> = {},
): void {
    const normalized = toError(error)

    if (debugMode) {
        console.warn(`[Error] ${code}:`, normalized, context)
    }

    if (sentry === null) {
        buffer(bufferedForSentry, normalized, code, context)
    } else {
        sendToSentry(normalized, code, context)
    }

    if (sink === null) {
        buffer(bufferedForSink, normalized, code, context)
    } else {
        sendToSink(normalized, code, context)
    }
}

/** Report an operational warning to Sentry without turning it into an exception. */
export function reportMessage(
    message: string,
    code: string,
    context: Record<string, unknown> = {},
): void {
    try {
        sentry?.captureMessage(message, {
            level: 'warning',
            tags: { error_code: code },
            contexts: { failure: context },
            fingerprint: ['{{ default }}', code],
        })
    } catch {
        // A reporting failure must not replace the warning it was observing.
    }
}

function buffer(
    queue: BufferedError[],
    error: Error,
    code: string,
    context: Record<string, unknown>,
): void {
    if (queue.length < MAX_BUFFERED_ERRORS) {
        queue.push({ error, code, context })
    }
}

function sendToSink(error: Error, code: string, context: Record<string, unknown>): void {
    try {
        sink?.(error, {
            error_code: code,
            $exception_source: 'browser',
            ...context,
        })
    } catch {
        // PostHog is blocked, opted out, or gone. Sentry still has this, and
        // throwing from here would break the page the visitor is on.
    }
}

function sendToSentry(error: Error, code: string, context: Record<string, unknown>): void {
    try {
        sentry?.captureException(error, {
            tags: { error_code: code },
            contexts: { failure: context },
            // Same reasoning as the server's fingerprint: group by our own
            // name for the failure, not by whichever frame happened to throw.
            fingerprint: ['{{ default }}', code],
        })
    } catch {
        // Sentry is down, blocked by an extension, or never loaded. PostHog
        // already has this error.
    }
}

/**
 * Catch what never reaches a try/catch: a promise nobody awaited, and an
 * error thrown outside any handler we wrote.
 *
 * Sentry's own integrations cover these once it has loaded. These handlers
 * are what carry the same failures into PostHog, and what covers the window
 * before the SDK arrives - or the environments where it never does.
 */
function installGlobalHandlers(): void {
    if (typeof window === 'undefined') {
        return
    }

    window.addEventListener('unhandledrejection', (event) => {
        reportError(event.reason, 'unhandled_rejection', {
            subsystem: 'browser',
            url: window.location.pathname,
        })
    })

    window.addEventListener(
        'error',
        (event) => {
            /*
             * A failed <img>/<script> load fires this too, with no `error` on
             * it. Image failures are not actionable here: aborted navigation,
             * expired temporary render URLs, and transient network failures all
             * look identical to a broken URL. Components may handle their own
             * image errors when they have enough context to recover, but the
             * global reporter must not turn every failed image request into an
             * exception. Executable assets remain actionable and are reported.
             */
            if (!event.error) {
                const target = event.target as HTMLElement | null

                if (target && target !== (window as unknown as HTMLElement) && 'tagName' in target) {
                    if (target.tagName.toLowerCase() === 'img') {
                        return
                    }

                    const tag = target.tagName.toLowerCase()
                    const source =
                        tag === 'link'
                            ? (target as HTMLLinkElement).href
                            : (target as HTMLScriptElement).src
                    let assetUrl = source || window.location.href

                    try {
                        assetUrl = new URL(assetUrl, window.location.href).href
                    } catch {
                        // Keep a malformed source reportable too.
                    }

                    const error = new Error(`Failed to load ${tag}`)
                    const context: AssetFailureContext = {
                        subsystem: 'browser',
                        tag,
                        url: window.location.pathname,
                        asset_url: assetUrl,
                    }

                    if (assetFailureHandler?.(error, context)) {
                        return
                    }

                    reportError(error, 'asset_load_failed', context)
                }

                return
            }

            reportError(event.error, 'uncaught_error', {
                subsystem: 'browser',
                url: window.location.pathname,
            })
        },
        // Capture phase: a failed asset load does not bubble to window.
        true,
    )
}

/** Anything can be thrown in JavaScript. Sentry needs an Error. */
function toError(value: unknown): Error {
    if (value instanceof Error) {
        return value
    }

    if (typeof value === 'string') {
        return new Error(value)
    }

    try {
        return new Error(JSON.stringify(value))
    } catch {
        return new Error(String(value))
    }
}

/** Whether the Sentry SDK has loaded and registered itself. */
export function isSentryReady(): boolean {
    return sentry !== null
}
