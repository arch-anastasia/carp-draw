import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
    clearVitePreloadReloadAttempt,
    handleVitePreloadError,
} from '@/plugins/preloadError'
import { blockedStorage } from './support/blockedStorage'

describe('Vite preload error recovery', () => {
    beforeEach(() => {
        sessionStorage.clear()
    })

    afterEach(() => {
        vi.restoreAllMocks()
    })

    it('reloads once without reporting the expected first failure', () => {
        const reload = vi.fn()
        const reportFailure = vi.fn()
        const firstError = new Event('vite:preloadError', { cancelable: true })

        handleVitePreloadError(firstError, sessionStorage, reload, reportFailure)

        expect(reload).toHaveBeenCalledOnce()
        expect(reportFailure).not.toHaveBeenCalled()
        expect(firstError.defaultPrevented).toBe(true)
    })

    it('reports a repeated failure without starting another reload', () => {
        const reload = vi.fn()
        const reportFailure = vi.fn()
        const failedUrl = 'https://example.com/build/assets/Studio-old.js'
        const firstError = new Event('vite:preloadError', { cancelable: true })
        const secondError = Object.assign(new Event('vite:preloadError', { cancelable: true }), {
            payload: new Error(`Failed to fetch dynamically imported module: ${failedUrl}`),
        })

        handleVitePreloadError(firstError, sessionStorage, reload, reportFailure)
        handleVitePreloadError(secondError, sessionStorage, reload, reportFailure)

        expect(reload).toHaveBeenCalledOnce()
        expect(reportFailure).toHaveBeenCalledOnce()
        expect(reportFailure).toHaveBeenCalledWith(failedUrl)
        expect(secondError.defaultPrevented).toBe(true)
    })

    it('allows another recovery reload after the replacement app starts', () => {
        const reload = vi.fn()

        handleVitePreloadError(new Event('vite:preloadError'), sessionStorage, reload)
        clearVitePreloadReloadAttempt()
        handleVitePreloadError(new Event('vite:preloadError'), sessionStorage, reload)

        expect(reload).toHaveBeenCalledTimes(2)
    })

    it('does not throw when the reporting sink is unavailable', () => {
        const reload = vi.fn()
        const unavailableReporter = vi.fn(() => {
            throw new Error('Sentry is unavailable')
        })

        handleVitePreloadError(new Event('vite:preloadError'), sessionStorage, reload)

        expect(() =>
            handleVitePreloadError(
                new Event('vite:preloadError'),
                sessionStorage,
                reload,
                unavailableReporter,
            ),
        ).not.toThrow()
        expect(reload).toHaveBeenCalledOnce()
    })

    it.each(['getItem', 'setItem'] as const)('reports without reloading when storage %s throws', (method) => {
        const reload = vi.fn()
        const reportFailure = vi.fn()
        const failedUrl = '/build/assets/Studio-abc.js'
        const event = Object.assign(new Event('vite:preloadError', { cancelable: true }), {
            payload: { url: failedUrl },
        })
        handleVitePreloadError(event, blockedStorage(method), reload, reportFailure)

        expect(reload).not.toHaveBeenCalled()
        expect(reportFailure).toHaveBeenCalledExactlyOnceWith(failedUrl)
        expect(event.defaultPrevented).toBe(true)
    })

    it('reports when the sessionStorage getter itself throws', () => {
        const reload = vi.fn()
        const reportFailure = vi.fn()
        vi.spyOn(window, 'sessionStorage', 'get').mockImplementation(() => {
            throw new Error('Storage blocked')
        })

        handleVitePreloadError(new Event('vite:preloadError'), undefined, reload, reportFailure)

        expect(reload).not.toHaveBeenCalled()
        expect(reportFailure).toHaveBeenCalledOnce()
        expect(() => clearVitePreloadReloadAttempt()).not.toThrow()
    })

    it('does not break a successful init when clearing storage throws', () => {
        vi.spyOn(sessionStorage, 'removeItem').mockImplementation(() => {
            throw new Error('Storage blocked')
        })

        expect(() => clearVitePreloadReloadAttempt()).not.toThrow()
    })
})
