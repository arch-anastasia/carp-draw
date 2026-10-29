import * as Sentry from '@sentry/vue'
import type { App } from 'vue'
import { scrubTelemetry } from './telemetryPrivacy'
import { registerSentry } from '@/plugins/errorReporter'
import type { SentryConfig } from '@/types/analytics'

/**
 * The Sentry SDK, isolated behind its own module.
 *
 * Every static import of `@sentry/vue` in the application is in this file,
 * which is what lets Vite put the 90KB SDK in a chunk of its own. The Inertia
 * bundle imports this module directly - it is an authenticated product
 * surface where a render error matters more than a kilobyte. The marketing
 * bundle imports it with `await import()` after the page is interactive, so a
 * landing page is not made slower by the thing that watches it.
 *
 * Whichever way it arrives, it registers itself with the reporter and any
 * errors raised in the meantime are replayed into it.
 */

let initialized = false

/**
 * Start Sentry. Returns false when there is no DSN, which is the normal case
 * outside production and is not a failure.
 *
 * `app` is optional: Inertia pages pass the Vue app so component render
 * errors are caught without attaching props, and the Blade marketing bundle
 * has no Vue app to pass.
 */
export function initSentry(config?: SentryConfig | null, app?: App, debug = false): boolean {
    if (initialized) {
        return true
    }

    if (!config?.dsn) {
        if (debug) {
            console.log('[Sentry] Skipped: no browser DSN configured')
        }

        return false
    }

    try {
        Sentry.init({
            app,
            dsn: config.dsn,
            environment: config.environment ?? undefined,
            release: config.release ?? undefined,

            /*
             * False, and it must stay false. It would attach the visitor's IP
             * and request headers, and this application's whole privacy
             * promise is about what it does not keep. The user id is attached
             * deliberately by the reporter instead.
             */
            sendDefaultPii: false,

            tracesSampleRate: config.tracesSampleRate,
            ignoreErrors: config.ignoreErrors,

            /*
             * FALSE, and not for bundle reasons.
             *
             * The drawing workflow holds a customer upload in component state as
             * a data URI. With props attached, any render error on that page
             * would have uploaded a private customer upload to Sentry
             * - the same leak `max_request_body_size: none` closes on the
             * server, arriving through the front door instead.
             *
             * The component name and the lifecycle hook, which is the part
             * that actually helps, are attached by the Vue error handler in
             * app.ts by hand.
             */
            attachProps: false,
            beforeSend(event) {
                if (event.request) {
                    delete event.request.data
                    delete event.request.headers
                    delete event.request.cookies
                    delete event.request.query_string
                }
                // Optional monitoring must never forward arbitrary component
                // state or the Axios request that caused an error.
                delete event.extra
                return scrubTelemetry(event) as typeof event
            },
            beforeBreadcrumb(breadcrumb) {
                if (breadcrumb.category?.startsWith('ui.') || breadcrumb.category === 'console') return null
                return scrubTelemetry(breadcrumb) as typeof breadcrumb
            },
        })

        initialized = true

        registerSentry({
            captureException: (error, hint) => Sentry.captureException(error, hint),
            captureMessage: (message, context) => Sentry.captureMessage(message, context),
            setUser: (user) => Sentry.setUser(user),
        })

        if (debug) {
            console.log('[Sentry] Initialized', { environment: config.environment })
        }

        return true
    } catch (error) {
        // Never let the crash reporter be the crash.
        console.warn('[Sentry] Failed to initialize:', error)

        return false
    }
}
