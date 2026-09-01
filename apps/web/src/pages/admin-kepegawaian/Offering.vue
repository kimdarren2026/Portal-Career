<script setup lang="ts">
/** Admin Kepegawaian — campus offer list (v8). Read only; offers are created
 *  and sent from the applicant detail page. Candidate accept/reject happens
 *  in the candidate's own portal. */
import { Head, Link } from '@inertiajs/vue3'
import AppShell from '@/layouts/AppShell.vue'
import { formatDateTime } from '@/lib/labels'

interface Row {
    id: number; application_id: number; application_code?: string | null; vacancy_title?: string | null
    status: string; sent_at: string | null; responded_at: string | null; created_at: string | null
}
const statusLabelMap: Record<string, string> = {
    DRAFT: 'Draf', SENT: 'Terkirim', ACCEPTED: 'Diterima', REJECTED: 'Ditolak', EXPIRED: 'Kedaluwarsa', WITHDRAWN: 'Ditarik',
}
const statusClass: Record<string, string> = {
    DRAFT: 'bg-slate-100 text-slate-700', SENT: 'bg-indigo-100 text-indigo-800',
    ACCEPTED: 'bg-green-100 text-green-800', REJECTED: 'bg-red-100 text-red-800',
    EXPIRED: 'bg-slate-200 text-slate-700', WITHDRAWN: 'bg-slate-200 text-slate-700',
}
defineProps<{ items: Row[]; pagination: { page: number; last_page: number } }>()
</script>

<template>
    <Head title="Offering" />
    <AppShell persona="admin-kepegawaian" active="kepegawaian/offering" title="Offering">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Offering</h1>
        <p class="mt-2 max-w-2xl text-slate-600">Penawaran untuk kandidat lowongan Karier di Kampus. Buat dan kirim penawaran dari halaman detail pelamar; kandidat merespons melalui portal kandidat.</p>

        <div v-if="!items.length" class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">Belum ada penawaran.</div>
        <div v-else class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">Pelamar</th><th class="px-4 py-3">Lowongan</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Dikirim</th><th class="px-4 py-3"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="item in items" :key="item.id">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ item.application_code ?? `#${item.application_id}` }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ item.vacancy_title ?? '—' }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusClass[item.status] ?? 'bg-slate-100 text-slate-700'">{{ statusLabelMap[item.status] ?? item.status }}</span></td>
                        <td class="px-4 py-3 text-slate-600">{{ formatDateTime(item.sent_at) }}</td>
                        <td class="px-4 py-3 text-right"><Link :href="`/kepegawaian/pelamar/${item.application_id}`" class="font-medium text-[#0061a5] hover:underline">Pelamar</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppShell>
</template>
