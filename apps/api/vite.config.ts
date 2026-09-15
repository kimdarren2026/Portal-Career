import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

/**
 * Vite runs from `apps/api` (the Laravel application root and single deployable)
 * and compiles frontend source from `apps/web` (build input only).
 * See SYSTEM_ARCHITECTURE.md §1 "Repository mapping".
 *
 * This keeps frontend and backend source in separate directories with separate
 * responsibilities, per PROJECT_STRUCTURE.md §22, while producing one artifact
 * the Laravel application serves.
 */
const here = path.dirname(fileURLToPath(import.meta.url))
const web = path.resolve(here, '../web/src')
const clientEntry = path.resolve(web, 'app.ts')
const ssrEntry = path.resolve(web, 'ssr.ts')

export default defineConfig({
    plugins: [
        laravel({
            // The frontend source is outside Vite's root. Use canonical
            // absolute paths so the dev server emits Vite's /@fs/ URL rather
            // than a browser-normalized `../web/...` URL that returns 404.
            input: [clientEntry],
            ssr: ssrEntry,
            refresh: true,
        }),
        vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } }),
        tailwindcss(),
    ],
    resolve: {
        alias: { '@': web },
    },
    // Frontend source lives outside this Vite root, and `node_modules` lives here
    // in apps/api. Bundling SSR dependencies avoids resolving bare imports from
    // apps/web, where no node_modules exists.
    ssr: {
        noExternal: true,
    },
    server: {
        host: '127.0.0.1',
        origin: 'http://127.0.0.1:5173',
        cors: {
            origin: [
                'http://127.0.0.1:8000',
                'http://localhost:8000',
                'http://127.0.0.1:8001',
                'http://localhost:8001',
            ],
        },
        fs: { allow: [path.resolve(here, '..')] },
    },
})
