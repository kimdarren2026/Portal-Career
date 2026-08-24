<script setup lang="ts">
/**
 * Bootstrap verification page.
 *
 * Exists only to prove the Laravel → Inertia → Vue → TypeScript → Tailwind →
 * Vite chain is wired correctly. It is NOT a product page and reproduces no
 * Stitch screen. Production UI is a later phase and must reproduce the frozen
 * Stitch visual system.
 */
defineProps<{
    application: string
    laravel: string
    php: string
    environment: string
}>()
</script>

<template>
    <main class="min-h-screen bg-slate-50 p-8 font-sans text-slate-900">
        <div class="mx-auto max-w-xl rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="text-xl font-semibold">{{ application }}</h1>
            <p class="mt-1 text-sm text-slate-600">Bootstrap verification — not a product page.</p>
            <dl class="mt-4 grid grid-cols-2 gap-y-2 text-sm">
                <dt class="font-medium text-slate-500">Laravel</dt>
                <dd>{{ laravel }}</dd>
                <dt class="font-medium text-slate-500">PHP</dt>
                <dd>{{ php }}</dd>
                <dt class="font-medium text-slate-500">Environment</dt>
                <dd>{{ environment }}</dd>
            </dl>
        </div>
    </main>
</template>
