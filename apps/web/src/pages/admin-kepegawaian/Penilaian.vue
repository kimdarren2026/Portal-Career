<script setup lang="ts">
/** Admin Kepegawaian — campus evaluation list (v8). Read only; evaluations
 *  are created and submitted from the applicant detail page. */
import { Head, Link } from '@inertiajs/vue3'
import AppShell from '@/layouts/AppShell.vue'
import { formatDateTime } from '@/lib/labels'

interface Row {
    id: number; application_id: number; application_code?: string | null; vacancy_title?: string | null
    recruitment_stage_id: number | null; recommendation: string | null; total_score: number | null
    submitted_at: string | null; created_at: string | null
}
defineProps<{ items: Row[]; pagination: { page: number; last_page: number } }>()
</script>

<template>
    <Head title="Penilaian" />
    <AppShell persona="admin-kepegawaian" active="kepegawaian/penilaian" title="Penilaian">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Penilaian</h1>
        <p class="mt-2 max-w-2xl text-slate-600">Penilaian kandidat lowongan Karier di Kampus. Buat dan kirim penilaian dari halaman detail pelamar.</p>

        <div v-if="!items.length" class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">Belum ada penilaian.</div>
        <div v-else class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">Pelamar</th><th class="px-4 py-3">Lowongan</th><th class="px-4 py-3">Rekomendasi</th><th class="px-4 py-3">Skor</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="item in items" :key="item.id">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ item.application_code ?? `#${item.application_id}` }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ item.vacancy_title ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ item.recommendation ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ item.total_score ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="item.submitted_at ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'">
                                {{ item.submitted_at ? 'Terkirim' : 'Draf' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right"><Link :href="`/kepegawaian/pelamar/${item.application_id}`" class="font-medium text-[#0061a5] hover:underline">Pelamar</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppShell>
</template>
