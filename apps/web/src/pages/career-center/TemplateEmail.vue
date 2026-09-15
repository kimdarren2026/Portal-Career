<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppShell from '@/layouts/AppShell.vue'

defineProps<{
    groups: Array<{ name: string; templates: string[] }>
    mode: 'CODE_MANAGED'
}>()
</script>

<template>
    <Head title="Template Email" />
    <AppShell persona="career-center" active="template-email" title="Template Email">
        <p class="page-eyebrow">Katalog read-only</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight text-[#002045]">Template Email</h1>
        <p class="mt-2 max-w-3xl text-slate-600">Template berikut sudah digunakan oleh sistem. Konten masih dikelola melalui kode, sehingga halaman ini tidak menyediakan perubahan yang seolah-olah tersimpan.</p>

        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
            Mode saat ini: <strong>read-only</strong>. Perubahan subjek, isi, dan variabel membutuhkan penyimpanan template yang belum diaktifkan.
        </div>

        <section class="mt-6 grid gap-5 lg:grid-cols-3">
            <article v-for="group in groups" :key="group.name" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-[#002045]">{{ group.name }}</h2>
                <ul class="mt-4 space-y-2">
                    <li v-for="template in group.templates" :key="template" class="rounded-xl bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700">{{ template }}</li>
                </ul>
            </article>
        </section>
    </AppShell>
</template>
