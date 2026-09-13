<script setup lang="ts">
/**
 * Super Admin "Template Workflow" — READ_ONLY_REFERENCE.
 *
 * No reusable workflow-template entity exists: there is no schema, no frozen
 * contract, and no stored global template data. The page states the current
 * supported model truthfully — recruitment stages are authored per vacancy —
 * and renders a proper empty/reference state. No template CRUD.
 */
import { Head } from '@inertiajs/vue3'
import AppShell from '@/layouts/AppShell.vue'

defineProps<{ global_templates: unknown[] }>()
</script>

<template>
    <Head title="Template Workflow" />
    <AppShell persona="super-admin" active="template-workflow" title="Template Workflow">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Template Workflow</h1>
        <p class="mt-2 max-w-2xl text-slate-600">
            Referensi model alur kerja seleksi yang berlaku saat ini.
        </p>

        <div class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
            <p class="text-sm font-medium text-slate-600">Belum ada template workflow global yang dikonfigurasi.</p>
            <p class="mx-auto mt-2 max-w-xl text-sm text-slate-500">
                Tidak ada entitas template alur kerja yang dapat digunakan ulang pada sistem ini.
            </p>
        </div>

        <section class="mt-8 max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-600 shadow-sm">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Model yang didukung saat ini</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>Tahapan seleksi (<em>recruitment stages</em>) dikonfigurasi per lowongan, bukan melalui template global.</li>
                <li>Setiap lowongan memiliki tahapannya sendiri yang dapat dibuat, diubah, diurutkan, dan dinonaktifkan oleh pemilik lowongan.</li>
            </ul>
        </section>
    </AppShell>
</template>
