import type { App } from 'vue'
import type { SentryConfig } from '@/types/analytics'
import { initErrorReporting, registerAssetFailureHandler, reportError } from './errorReporter'
import { clearVitePreloadReloadAttempt, handleAssetLoadFailure, registerVitePreloadErrorHandler } from './preloadError'

/** Shared boot sequence; no SDK download or telemetry traffic without a DSN. */
export function bootBrowserFoundation(config?: SentryConfig | null, app?: App): void {
    initErrorReporting()
    registerAssetFailureHandler(handleAssetLoadFailure)
    registerVitePreloadErrorHandler()

    if (app) {
        app.config.errorHandler = (error, _instance, info) => {
            reportError(error, 'vue_render_error', { lifecycle: info })
        }
    }

    if (config?.dsn) {
        const load = () => {
            void import('./sentry')
                .then(({ initSentry }) => initSentry(config, app))
                .catch((error: unknown) => reportError(error, 'sentry_init_failed'))
        }
        // Keep the optional SDK outside the first-paint path on both surfaces.
        window.setTimeout(load, 0)
    }
}

export { clearVitePreloadReloadAttempt as markBrowserReady }
