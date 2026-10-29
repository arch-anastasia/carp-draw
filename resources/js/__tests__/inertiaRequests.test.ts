import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import axios, { AxiosError, AxiosHeaders, CanceledError, type AxiosAdapter } from 'axios'
import { router } from '@inertiajs/vue3'
import { isPageLeaving, registerInertiaErrorHandlers } from '@/plugins/inertiaRequests'
import { registerExceptionSink, registerSentry } from '@/plugins/errorReporter'



const captureException = vi.fn()
const exceptionSink = vi.fn()
const originalAdapter = axios.defaults.adapter
let cleanup: () => void

const initialPage = {
    component: 'App/Dashboard', props: { errors: {} }, url: '/app',
    version: null, clearHistory: false, encryptHistory: false,
    flash: {}, rememberedState: {},
}

beforeAll(async () => {
    router.init({
        initialPage,
        resolveComponent: async () => ({}),
        swapComponent: async () => {},
    })
    await flushPromises()
})

beforeEach(() => {
    axios.defaults.adapter = async (config) => ({
        config, status: 200, statusText: 'OK', headers: { 'x-inertia': true }, data: initialPage,
    })
    vi.clearAllMocks()
    window.history.replaceState({}, '', '/app')
    sessionStorage.clear()
    registerSentry({ captureException, captureMessage: vi.fn(), setUser: vi.fn() })
    registerExceptionSink(exceptionSink)
    cleanup = registerInertiaErrorHandlers()
})

afterEach(async () => {
    await flushPromises()
    cleanup()
    axios.defaults.adapter = originalAdapter
    vi.restoreAllMocks()
})

function exception(error: unknown): CustomEvent {
    const event = new CustomEvent('inertia:exception', { cancelable: true, detail: { exception: error } })
    document.dispatchEvent(event)
    return event
}

function restorePage(): void {
    const event = new Event('pageshow')
    Object.defineProperty(event, 'persisted', { value: true })
    window.dispatchEvent(event)
}

function redirectAdapter(status = 409, headers: Record<string, string> = { 'x-inertia-location': 'https://checkout.stripe.com/c/pay/cs_test' }): AxiosAdapter {
    return async (config) => {
        throw new AxiosError('Redirect', undefined, config, undefined, {
            config, status, statusText: 'Conflict', data: '', headers: new AxiosHeaders(headers),
        })
    }
}

const transportErrors = [
    new AxiosError('Network Error', 'ERR_NETWORK'),
    new AxiosError('timeout', 'ECONNABORTED'),
    new AxiosError('timeout', 'ETIMEDOUT'),
    new CanceledError('canceled'),
    new AxiosError('Network Error', undefined, undefined, { status: 0 }),
    new AxiosError('Network Error', undefined, undefined, undefined, {
        status: 0, statusText: '', data: '', headers: {}, config: { headers: new AxiosHeaders() },
    }),
]

describe('Inertia request reporting', () => {
    it.each(transportErrors)('reports transport failures while the page is active: %s', (error) => {
        expect(exception(error).defaultPrevented).toBe(false)
        expect(captureException).toHaveBeenCalledWith(error, expect.objectContaining({
            tags: { error_code: 'inertia_request_exception' },
        }))
        expect(exceptionSink).toHaveBeenCalledOnce()
    })

    it.each(['beforeunload', 'pagehide'])('suppresses transport failures during %s without rethrowing them', (event) => {
        window.dispatchEvent(new Event(event))

        for (const error of transportErrors) {
            expect(exception(error).defaultPrevented).toBe(true)
        }

        expect(captureException).not.toHaveBeenCalled()
        expect(exceptionSink).not.toHaveBeenCalled()
    })

    it('does not suppress HTTP responses or programming errors during departure', () => {
        window.dispatchEvent(new Event('pagehide'))
        const responseError = new AxiosError('Network Error', 'ERR_NETWORK', undefined, { status: 0 }, {
            status: 500, statusText: 'Server Error', data: '', headers: {}, config: { headers: new AxiosHeaders() },
        })

        expect(exception(responseError).defaultPrevented).toBe(false)
        expect(exception(new TypeError('Broken callback')).defaultPrevented).toBe(false)
        expect(captureException).toHaveBeenCalledTimes(2)
        expect(exceptionSink).toHaveBeenCalledTimes(2)
    })

    it('reports again when the page is restored from the back/forward cache', () => {
        window.dispatchEvent(new Event('pagehide'))
        restorePage()

        expect(isPageLeaving()).toBe(false)
        expect(exception(transportErrors[0]).defaultPrevented).toBe(false)
        expect(captureException).toHaveBeenCalledOnce()
    })

    it('keeps invalid server responses reportable while leaving', () => {
        window.dispatchEvent(new Event('pagehide'))
        const event = new CustomEvent('inertia:invalid', { cancelable: true, detail: { response: { status: 500 } } })
        document.dispatchEvent(event)

        expect(event.defaultPrevented).toBe(false)
        expect(captureException).toHaveBeenCalledWith(new Error('Non-Inertia response (500)'), expect.objectContaining({
            tags: { error_code: 'inertia_invalid_response' },
        }))
    })

    it.each([
        [409, {}, true],
        [500, { 'x-inertia-location': 'https://checkout.stripe.com' }, true],
        [409, { 'x-inertia-location': 'https://checkout.stripe.com' }, false],
    ])('does not treat unrelated responses as a departure (%s, %j, Inertia=%s)', async (status, headers, inertia) => {
        await expect(axios.post('/billing/subscribe', {}, {
            adapter: redirectAdapter(status, headers),
            headers: inertia ? { 'X-Inertia': true } : {},
        })).rejects.toBeInstanceOf(AxiosError)

        expect(isPageLeaving()).toBe(false)
        expect(exception(transportErrors[0]).defaultPrevented).toBe(false)
        expect(captureException).toHaveBeenCalledOnce()
    })

    it('removes listeners and the redirect interceptor on cleanup', async () => {
        cleanup()
        window.dispatchEvent(new Event('pagehide'))
        await expect(axios.post('/billing/subscribe', {}, {
            adapter: redirectAdapter(), headers: { 'X-Inertia': true },
        })).rejects.toBeInstanceOf(AxiosError)

        expect(isPageLeaving()).toBe(false)
        exception(transportErrors[0])
        expect(captureException).not.toHaveBeenCalled()
    })
})

it('marks a real Inertia external redirect before reporting its navigation abort', async () => {
    await expect(axios.post('/billing/subscribe', {}, {
        adapter: redirectAdapter(), headers: { 'X-Inertia': true },
    })).rejects.toBeInstanceOf(AxiosError)
    expect(isPageLeaving()).toBe(true)
    expect(exception(transportErrors[0]).defaultPrevented).toBe(true)
    expect(captureException).not.toHaveBeenCalled()
})
