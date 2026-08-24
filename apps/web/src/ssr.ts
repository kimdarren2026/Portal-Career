/**
 * Inertia SSR entry point (ADR-017).
 *
 * Runs as the fourth runtime process — a Node renderer that belongs to the same
 * application and release. It holds no business logic, no database connection,
 * and no API surface; it renders the same compiled components the browser would,
 * one request earlier.
 *
 * Public, SEO-sensitive pages are server-rendered through this entry. If the
 * renderer is unavailable, Inertia falls back to client-side rendering: the
 * application keeps working and authenticated portals are unaffected, but public
 * pages lose their indexable HTML. That is a silent degradation and must be
 * monitored (DEPLOYMENT_ARCHITECTURE.md §5).
 *
 * Components rendered here must be SSR-safe: no direct `window` or `document`
 * access during render.
 */
import { createSSRApp, h, type DefineComponent } from 'vue'
import { renderToString } from '@vue/server-renderer'
import { createInertiaApp } from '@inertiajs/vue3'
import createServer from '@inertiajs/vue3/server'

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => (title ? `${title} — Portal Karir Kampus` : 'Portal Karir Kampus'),
        resolve: (name) => {
            const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue', { eager: true })
            const p = pages[`./pages/${name}.vue`]
            if (!p) {
                throw new Error(`Inertia page not found: ${name}`)
            }
            return p
        },
        setup({ App, props, plugin }) {
            return createSSRApp({ render: () => h(App, props) }).use(plugin)
        },
    }),
)
