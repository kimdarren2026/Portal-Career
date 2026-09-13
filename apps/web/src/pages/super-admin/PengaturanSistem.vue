<script setup lang="ts">
/**
 * Super Admin "Pengaturan Sistem" — READ_ONLY_REFERENCE.
 *
 * A safe, non-secret configuration overview. No `system_settings` table, no
 * generic key/value editor. The server never sends APP_KEY, DB password, DSN,
 * SMTP secret, object-storage keys, or tokens — only capability names and
 * configured/not-configured status.
 */
import { Head } from '@inertiajs/vue3'
import AppShell from '@/layouts/AppShell.vue'

interface Overview {
    application: { name: string; environment: string; locale: string; timezone: string }
    capabilities: Array<{ label: string; value: string }>
    super_admin_modules_active: string[]
    frozen_vocabularies: Array<{ label: string; count: number }>
}
defineProps<{ overview: Overview }>()
</script>

<template>
    <Head title="Pengaturan Sistem" />
    <AppShell persona="super-admin" active="pengaturan-sistem" title="Pengaturan Sistem">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Pengaturan Sistem</h1>
        <p class="mt-2 max-w-2xl text-slate-600">
            Ringkasan konfigurasi sistem yang aman ditampilkan. Bersifat <span class="font-semibold">hanya-baca</span> —
            tidak ada nilai rahasia (kunci aplikasi, kata sandi basis data, kredensial SMTP/penyimpanan).
        </p>

        <section class="mt-6 grid max-w-4xl gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Aplikasi</p>
                <p class="mt-1 font-bold text-[#002045]">{{ overview.application.name }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Environment</p>
                <p class="mt-1 font-bold text-[#002045]">{{ overview.application.environment }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Locale</p>
                <p class="mt-1 font-bold text-[#002045]">{{ overview.application.locale }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Zona waktu</p>
                <p class="mt-1 font-bold text-[#002045]">{{ overview.application.timezone }}</p>
            </div>
        </section>

        <section class="mt-8 max-w-2xl overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">Kapabilitas</th><th class="px-4 py-3">Status</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="c in overview.capabilities" :key="c.label" class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-[#002045]">{{ c.label }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ c.value }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="mt-8 grid max-w-4xl gap-6 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Modul Super Admin aktif</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-600">
                    <li v-for="m in overview.super_admin_modules_active" :key="m">{{ m }}</li>
                </ul>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kosakata beku</h2>
                <ul class="mt-2 space-y-1 text-sm text-slate-600">
                    <li v-for="v in overview.frozen_vocabularies" :key="v.label" class="flex justify-between gap-4">
                        <span>{{ v.label }}</span><span class="font-mono text-slate-400">{{ v.count }}</span>
                    </li>
                </ul>
            </div>
        </section>
    </AppShell>
</template>
