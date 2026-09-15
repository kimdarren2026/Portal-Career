<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppShell from '@/layouts/AppShell.vue'

const props = defineProps<{
    counts: { external_apply_started?: number; external_apply_confirmed?: number; outcomes_recorded?: number }
    outcomes_by_type?: Record<string, number>
}>()

const outcomeRows = computed(() => Object.entries(props.outcomes_by_type ?? {}).sort((a, b) => b[1] - a[1]))
</script>

<template>
    <Head title="Alumni & Outcome" />
    <AppShell persona="career-center" active="alumni-outcome" title="Alumni & Outcome">
        <p class="page-eyebrow">Pemantauan agregat</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight text-[#002045]">Alumni & Outcome</h1>
        <p class="mt-2 max-w-3xl text-slate-600">Ringkasan proses lamaran eksternal dan outcome yang telah dikonfirmasi. Halaman ini tidak menampilkan identitas maupun data seleksi kandidat.</p>

        <section class="mt-6 grid gap-4 sm:grid-cols-3" aria-label="Ringkasan outcome eksternal">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><p class="text-sm font-semibold text-slate-600">External Apply Dimulai</p><p class="mt-2 text-4xl font-bold text-[#002045]">{{ counts.external_apply_started ?? 0 }}</p></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><p class="text-sm font-semibold text-slate-600">External Apply Dikonfirmasi</p><p class="mt-2 text-4xl font-bold text-[#002045]">{{ counts.external_apply_confirmed ?? 0 }}</p></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><p class="text-sm font-semibold text-slate-600">Outcome Tercatat</p><p class="mt-2 text-4xl font-bold text-[#002045]">{{ counts.outcomes_recorded ?? 0 }}</p></div>
        </section>

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-bold text-[#002045]">Outcome berdasarkan jenis</h2>
            <p v-if="outcomeRows.length === 0" class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">Belum ada outcome eksternal yang dapat direkap.</p>
            <ul v-else class="mt-5 grid gap-3 sm:grid-cols-2">
                <li v-for="entry in outcomeRows" :key="entry[0]" class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3"><span class="font-semibold text-slate-700">{{ entry[0] }}</span><span class="text-2xl font-bold text-[#002045]">{{ entry[1] }}</span></li>
            </ul>
        </section>
    </AppShell>
</template>
