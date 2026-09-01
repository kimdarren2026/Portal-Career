<script setup lang="ts">
/** Admin Kepegawaian — campus recruitment outcomes (v8). Recorded + the H-5
 *  incomplete list. Outcomes are recorded explicitly (never automatically). */
import { Head, Link } from '@inertiajs/vue3'
import AppShell from '@/layouts/AppShell.vue'
import { formatDateTime } from '@/lib/labels'

interface OutcomeRow { id: number; application_id: number; application_code?: string | null; vacancy_title?: string | null; outcome: string; confirmed_at: string | null; created_at: string | null }
interface IncompleteRow { application_id?: number; id?: number; application_code?: string | null; vacancy_title?: string | null; current_status?: string }
interface Page<T> { items: T[]; pagination: { page: number; last_page: number } }

const outcomeLabel: Record<string, string> = {
    HIRED: 'Diterima Bekerja', REJECTED: 'Tidak Lolos', WITHDRAWN: 'Mengundurkan Diri', NO_SHOW: 'Tidak Hadir',
}
defineProps<{ recorded: Page<OutcomeRow>; incomplete: Page<IncompleteRow>; filters: Record<string, string> }>()
</script>

<template>
    <Head title="Outcome Rekrutmen" />
    <AppShell persona="admin-kepegawaian" active="kepegawaian/outcome-rekrutmen" title="Outcome Rekrutmen">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Outcome Rekrutmen</h1>
        <p class="mt-2 max-w-2xl text-slate-600">Outcome dicatat secara eksplisit dari halaman detail pelamar setelah aplikasi berstatus terminal.</p>

        <section class="mt-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Belum Dicatat</h2>
            <div v-if="!incomplete.items.length" class="mt-3 rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-500">Tidak ada aplikasi terminal yang belum dicatat.</div>
            <div v-else class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr><th class="px-4 py-3">Pelamar</th><th class="px-4 py-3">Lowongan</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in incomplete.items" :key="row.application_id ?? row.id">
                            <td class="px-4 py-3 font-medium text-slate-800">{{ row.application_code ?? `#${row.application_id ?? row.id}` }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.vacancy_title ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.current_status ?? '—' }}</td>
                            <td class="px-4 py-3 text-right"><Link :href="`/kepegawaian/pelamar/${row.application_id ?? row.id}`" class="font-medium text-[#0061a5] hover:underline">Catat</Link></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="mt-8">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Sudah Dicatat</h2>
            <div v-if="!recorded.items.length" class="mt-3 rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-500">Belum ada outcome tercatat.</div>
            <div v-else class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr><th class="px-4 py-3">Pelamar</th><th class="px-4 py-3">Lowongan</th><th class="px-4 py-3">Outcome</th><th class="px-4 py-3">Dicatat</th><th class="px-4 py-3"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in recorded.items" :key="row.id">
                            <td class="px-4 py-3 font-medium text-slate-800">{{ row.application_code ?? `#${row.application_id}` }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.vacancy_title ?? '—' }}</td>
                            <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">{{ outcomeLabel[row.outcome] ?? row.outcome }}</span></td>
                            <td class="px-4 py-3 text-slate-600">{{ formatDateTime(row.confirmed_at ?? row.created_at) }}</td>
                            <td class="px-4 py-3 text-right"><Link :href="`/kepegawaian/pelamar/${row.application_id}`" class="font-medium text-[#0061a5] hover:underline">Pelamar</Link></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppShell>
</template>
