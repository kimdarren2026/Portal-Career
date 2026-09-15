<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppShell from '@/layouts/AppShell.vue'

defineProps<{ rules: Array<{ label: string; value: string }> }>()
</script>

<template>
    <Head title="Pengaturan Moderasi" />
    <AppShell persona="career-center" active="pengaturan-moderasi" title="Pengaturan Moderasi">
        <p class="page-eyebrow">Kebijakan efektif</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight text-[#002045]">Pengaturan Moderasi</h1>
        <p class="mt-2 max-w-3xl text-slate-600">Konfigurasi yang saat ini diberlakukan sistem pada verifikasi perusahaan dan moderasi lowongan.</p>

        <div class="mt-6 rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-blue-900">
            Halaman ini read-only. Aturan di bawah masih merupakan kebijakan tetap aplikasi dan belum memiliki penyimpanan konfigurasi dinamis.
        </div>

        <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <dl class="divide-y divide-slate-100">
                <div v-for="rule in rules" :key="rule.label" class="grid gap-2 px-5 py-4 sm:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] sm:px-6">
                    <dt class="font-semibold text-[#002045]">{{ rule.label }}</dt>
                    <dd class="text-sm leading-6 text-slate-600">{{ rule.value }}</dd>
                </div>
            </dl>
        </section>

        <div class="mt-6 flex flex-wrap gap-3">
            <Link href="/verifikasi-perusahaan" class="rounded-xl bg-[#0061a5] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#004f87]">Verifikasi perusahaan</Link>
            <Link href="/moderasi-lowongan" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:border-[#0061a5]">Moderasi lowongan</Link>
        </div>
    </AppShell>
</template>
