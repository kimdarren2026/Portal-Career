<script setup lang="ts">
/** Admin Kepegawaian — campus selection-schedule list (v8). Read only;
 *  schedules are created from the applicant detail page. */
import { Head, Link } from '@inertiajs/vue3'
import AppShell from '@/layouts/AppShell.vue'
import { formatDateTime, scheduleMethodLabel, scheduleStatusLabel, statusLabel } from '@/lib/labels'

interface Row {
    id: number; application_id: number; selection_type: string
    starts_at: string | null; timezone: string | null; method: string; status: string
}
defineProps<{ items: Row[]; pagination: { page: number; last_page: number } }>()
</script>

<template>
    <Head title="Jadwal Seleksi" />
    <AppShell persona="admin-kepegawaian" active="kepegawaian/jadwal-seleksi" title="Jadwal Seleksi">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Jadwal Seleksi</h1>
        <p class="mt-2 max-w-2xl text-slate-600">Jadwal seleksi lowongan Karier di Kampus. Jadwal baru dibuat dari halaman detail pelamar. Waktu ditampilkan sesuai zona waktu jadwal.</p>

        <div v-if="!items.length" class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">Belum ada jadwal seleksi.</div>
        <div v-else class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">Jenis Seleksi</th><th class="px-4 py-3">Waktu</th><th class="px-4 py-3">Metode</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="item in items" :key="item.id">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ item.selection_type }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ formatDateTime(item.starts_at, item.timezone) }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ scheduleMethodLabel[item.method] ?? item.method }}</td>
                        <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ statusLabel(scheduleStatusLabel, item.status) }}</span></td>
                        <td class="px-4 py-3 text-right"><Link :href="`/kepegawaian/pelamar/${item.application_id}`" class="font-medium text-[#0061a5] hover:underline">Pelamar</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppShell>
</template>
