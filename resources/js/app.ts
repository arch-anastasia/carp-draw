import '../css/app.css'

import { createApp, h, type DefineComponent } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { ZiggyVue } from 'ziggy-js'
import { bootBrowserFoundation, markBrowserReady } from './plugins/browserFoundation'
import { registerInertiaErrorHandlers } from './plugins/inertiaRequests'
import type { SentryConfig } from './types/analytics'

// Install recovery before the initial page chunk is requested.
bootBrowserFoundation()

createInertiaApp({
    title: (title) => (title ? `${title} | CarpDraw AI` : 'CarpDraw AI'),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue')
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
        bootBrowserFoundation(props.initialPage.props.sentry as SentryConfig | undefined, app)
        registerInertiaErrorHandlers()
        app.mount(el)
        markBrowserReady()
    },
    progress: {
        color: '#f5820b',
    },
})
