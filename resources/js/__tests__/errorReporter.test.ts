import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
    initErrorReporting,
    reportMessage,
    registerExceptionSink,
    registerAssetFailureHandler,
    registerSentry,
} from '@/plugins/errorReporter'
import { handleAssetLoadFailure, handleVitePreloadError } from '@/plugins/preloadError'
import { blockedStorage } from './support/blockedStorage'

describe('global error reporting', () => {
    it('ignores failed images without hiding executable asset failures', () => {
        const captureException = vi.fn()
        const exceptionSink = vi.fn()

        registerSentry({ captureException, captureMessage: vi.fn(), setUser: vi.fn() })
        registerExceptionSink(exceptionSink)
        initErrorReporting()

        const image = document.body.appendChild(document.createElement('img'))
        image.dispatchEvent(new Event('error'))

        expect(captureException).not.toHaveBeenCalled()
        expect(exceptionSink).not.toHaveBeenCalled()

        const script = document.body.appendChild(document.createElement('script'))
        script.dispatchEvent(new Event('error'))

        expect(captureException).toHaveBeenCalledOnce()
        expect(captureException.mock.calls[0][0]).toEqual(new Error('Failed to load script'))
        expect(captureException.mock.calls[0][1]).toMatchObject({
            tags: { error_code: 'asset_load_failed' },
        })
        expect(exceptionSink).toHaveBeenCalledOnce()
    })

    it('sends operational warnings as low-severity tagged messages', () => {
        const captureMessage = vi.fn()

        registerSentry({ captureException: vi.fn(), captureMessage, setUser: vi.fn() })
        reportMessage('Vite preload failed after recovery reload', 'chunk_load_failure', {
            url: '/build/assets/Studio-old.js',
        })

        expect(captureMessage).toHaveBeenCalledWith(
            'Vite preload failed after recovery reload',
            expect.objectContaining({
                level: 'warning',
                tags: { error_code: 'chunk_load_failure' },
                contexts: { failure: { url: '/build/assets/Studio-old.js' } },
            }),
        )
    })
})

describe('asset failure classification and recovery', () => {
    const captureException = vi.fn()
    const captureMessage = vi.fn()
    const exceptionSink = vi.fn()
    const reload = vi.fn()

    function failAsset(tag: 'script' | 'link', source: string): void {
        const element = document.createElement(tag)

        if (element instanceof HTMLLinkElement) {
            element.rel = 'modulepreload'
        }

        // Supply the browser's resolved property without asking happy-dom to
        // fetch it too: each test dispatches exactly the failure it exercises.
        Object.defineProperty(element, tag === 'link' ? 'href' : 'src', {
            value: source ? new URL(source, window.location.href).href : '',
        })

        document.head.appendChild(element)
        element.dispatchEvent(new Event('error'))
        element.remove()
    }

    beforeEach(() => {
        vi.clearAllMocks()
        sessionStorage.clear()
        registerSentry({ captureException, captureMessage, setUser: vi.fn() })
        registerExceptionSink(exceptionSink)
        registerAssetFailureHandler((error, context) =>
            handleAssetLoadFailure(error, context, undefined, reload),
        )
        initErrorReporting()
    })

    afterEach(() => {
        vi.restoreAllMocks()
        registerAssetFailureHandler(() => false)
    })

    it.each([
        ['script', '/build/assets/app-abc.js'],
        ['link', '/build/assets/Studio-abc.js'],
    ] as const)('recovers a failed %s once and preserves the broken-deploy canary', (tag, source) => {
        failAsset(tag, source)

        expect(reload).toHaveBeenCalledOnce()
        expect(sessionStorage.getItem('vite_preload_reload_attempted')).toBe('1')
        expect(captureException).not.toHaveBeenCalled()
        expect(captureMessage).not.toHaveBeenCalled()
        expect(exceptionSink).not.toHaveBeenCalled()

        failAsset(tag, source)

        expect(reload).toHaveBeenCalledOnce()
        expect(captureException).toHaveBeenCalledExactlyOnceWith(
            new Error(`Failed to load ${tag}`),
            expect.objectContaining({
                tags: { error_code: 'asset_load_failed' },
                contexts: {
                    failure: {
                        subsystem: 'browser',
                        tag,
                        url: window.location.pathname,
                        asset_url: new URL(source, window.location.href).href,
                    },
                },
            }),
        )
        expect(exceptionSink).toHaveBeenCalledOnce()
    })

    it('reports a blocked third-party script as a warning without spending recovery', () => {
        const source = 'https://eu-assets.i.posthog.com/array.js'

        failAsset('script', source)

        expect(reload).not.toHaveBeenCalled()
        expect(sessionStorage.getItem('vite_preload_reload_attempted')).toBeNull()
        expect(captureException).not.toHaveBeenCalled()
        expect(exceptionSink).not.toHaveBeenCalled()
        expect(captureMessage).toHaveBeenCalledExactlyOnceWith(
            'Failed to load script',
            expect.objectContaining({
                level: 'warning',
                tags: { error_code: 'third_party_asset_blocked' },
                contexts: {
                    failure: {
                        subsystem: 'browser',
                        tag: 'script',
                        url: window.location.pathname,
                        asset_url: source,
                        host: 'eu-assets.i.posthog.com',
                    },
                },
            }),
        )
    })

    it.each(['/legacy.js', '/build-other/app.js', ''])('reports %s without reloading', (source) => {
        failAsset('script', source)

        expect(reload).not.toHaveBeenCalled()
        expect(captureException).toHaveBeenCalledExactlyOnceWith(
            new Error('Failed to load script'),
            expect.objectContaining({
                tags: { error_code: 'asset_load_failed' },
                contexts: {
                    failure: expect.objectContaining({
                        asset_url: new URL(source, window.location.href).href,
                    }),
                },
            }),
        )
    })

    it('never reloads a cross-origin URL even when its path is under /build/', () => {
        failAsset('link', 'https://cdn.example.com/build/assets/Studio-abc.js')

        expect(reload).not.toHaveBeenCalled()
        expect(captureException).not.toHaveBeenCalled()
        expect(captureMessage).toHaveBeenCalledOnce()
    })

    it('shares the reload guard from an element failure to a Vite failure', () => {
        const reportFailure = vi.fn()

        failAsset('script', '/build/assets/app-abc.js')
        handleVitePreloadError(new Event('vite:preloadError'), sessionStorage, reload, reportFailure)

        expect(reload).toHaveBeenCalledOnce()
        expect(reportFailure).toHaveBeenCalledOnce()
    })

    it('shares the reload guard from a Vite failure to an element failure', () => {
        handleVitePreloadError(new Event('vite:preloadError'), sessionStorage, reload)
        failAsset('script', '/build/assets/app-abc.js')

        expect(reload).toHaveBeenCalledOnce()
        expect(captureException).toHaveBeenCalledOnce()
    })

    it.each(['getItem', 'setItem'] as const)('reports without reloading when storage %s throws', (method) => {
        registerAssetFailureHandler((error, context) =>
            handleAssetLoadFailure(error, context, blockedStorage(method), reload),
        )

        failAsset('script', '/build/assets/app-abc.js')

        expect(reload).not.toHaveBeenCalled()
        expect(captureException).toHaveBeenCalledOnce()
        expect(exceptionSink).toHaveBeenCalledOnce()
    })

    it('reports without reloading when accessing sessionStorage throws', () => {
        vi.spyOn(window, 'sessionStorage', 'get').mockImplementation(() => {
            throw new Error('Storage blocked')
        })

        failAsset('script', '/build/assets/app-abc.js')

        expect(reload).not.toHaveBeenCalled()
        expect(captureException).toHaveBeenCalledOnce()
    })

    it('still ignores images when recovery is registered', () => {
        const image = document.body.appendChild(document.createElement('img'))
        image.src = '/build/assets/photo.jpg'
        image.dispatchEvent(new Event('error'))
        image.remove()

        expect(reload).not.toHaveBeenCalled()
        expect(captureException).not.toHaveBeenCalled()
        expect(captureMessage).not.toHaveBeenCalled()
        expect(exceptionSink).not.toHaveBeenCalled()
    })
})
