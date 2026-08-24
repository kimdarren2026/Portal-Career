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

export default defineConfig({
    plugins: [
        laravel({
            input: ['../web/src/app.ts'],
            ssr: '../web/src/ssr.ts',
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
        fs: { allow: [path.resolve(here, '..')] },
    },
})
