/**
 * Inertia client entry point.
 *
 * Frontend source lives in `apps/web` and is compiled by Vite running from
 * `apps/api`; the bundle is served by the Laravel application
 * (SYSTEM_ARCHITECTURE.md §1). This is a build input, not a deployable.
 */
import { createApp, h, type DefineComponent } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import './app.css'

createInertiaApp({
    title: (title) => (title ? `${title} — Portal Karir Kampus` : 'Portal Karir Kampus'),
    resolve: (name) => {
        const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue', { eager: true })
        const page = pages[`./pages/${name}.vue`]
        if (!page) {
            throw new Error(`Inertia page not found: ${name}`)
        }
        return page
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el)
    },
})
