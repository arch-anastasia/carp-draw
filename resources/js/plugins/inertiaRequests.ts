import axios from 'axios'
import { router } from '@inertiajs/vue3'
import { reportError } from '@/plugins/errorReporter'

let leaving = false

export function isPageLeaving(): boolean {
    return leaving
}

function isNavigationRequestAbort(error: unknown): boolean {
    if (!leaving || !axios.isAxiosError(error) || (error.response && error.response.status !== 0)) {
        return false
    }

    return axios.isCancel(error)
        || ['ERR_NETWORK', 'ECONNABORTED', 'ETIMEDOUT', 'ERR_CANCELED'].includes(error.code ?? '')
        || error.response?.status === 0
        || error.request?.status === 0
}

/** Observe redirects before Inertia handles the 409 and fires onFinish. */
export function registerInertiaErrorHandlers(): () => void {
    const markLeaving = () => { leaving = true }
    const reset = () => { leaving = false }

    window.addEventListener('beforeunload', markLeaving)
    window.addEventListener('pagehide', markLeaving)
    window.addEventListener('pageshow', reset)

    const interceptor = axios.interceptors.response.use(undefined, (error: unknown) => {
        if (axios.isAxiosError(error)
            && error.config?.headers?.['X-Inertia']
            && error.response?.status === 409
            && error.response.headers['x-inertia-location']) {
            markLeaving()
        }

        return Promise.reject(error)
    })

    const removeExceptionHandler = router.on('exception', (event) => {
        if (isNavigationRequestAbort(event.detail.exception)) {
            // Inertia would otherwise rethrow this as an unhandled rejection.
            event.preventDefault()
            return
        }

        reportError(event.detail.exception, 'inertia_request_exception', {
            subsystem: 'inertia',
            url: window.location.pathname,
        })
    })

    const removeInvalidHandler = router.on('invalid', (event) => {
        reportError(
            new Error(`Non-Inertia response (${event.detail.response.status})`),
            'inertia_invalid_response',
            {
                subsystem: 'inertia',
                status: event.detail.response.status,
                url: window.location.pathname,
            },
        )
    })

    return () => {
        window.removeEventListener('beforeunload', markLeaving)
        window.removeEventListener('pagehide', markLeaving)
        window.removeEventListener('pageshow', reset)
        axios.interceptors.response.eject(interceptor)
        removeExceptionHandler()
        removeInvalidHandler()
        reset()
    }
}
