<script setup lang="ts">
/** Read-only candidate schedule detail + history. Candidate-safe fields only — no actor_user_id, no internal reason, no raw previous_snapshot. */
import { Head, Link } from '@inertiajs/vue3'
import AppShell from '@/layouts/AppShell.vue'
import { formatDateTime, scheduleEventLabel, scheduleMethodLabel, scheduleStatusLabel, statusLabel } from '@/lib/labels'

interface ScheduleDetail {
    id: number
    application_id: number
    selection_type: string
    starts_at: string | null
    ends_at: string | null
    timezone: string | null
    method: string
    location: string | null
    meeting_url: string | null
    instructions: string | null
    status: string
    stage_label: string | null
}
interface HistoryEvent { event_type: string; occurred_at: string | null }

defineProps<{ schedule: ScheduleDetail; history: HistoryEvent[] }>()
</script>

<template>
    <Head title="Detail Jadwal Seleksi" />
    <AppShell persona="candidate" active="jadwal-seleksi" title="Detail Jadwal">
        <Link href="/jadwal-seleksi" class="text-sm font-medium text-[#0061a5] hover:underline">← Kembali ke Jadwal Seleksi</Link>

        <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
            <h1 class="text-3xl font-bold tracking-tight text-[#002045]">{{ schedule.stage_label ?? schedule.selection_type }}</h1>
            <span class="rounded-full bg-slate-100 px-3 py-1.5 text-sm font-semibold text-slate-700">{{ statusLabel(scheduleStatusLabel, schedule.status) }}</span>
        </div>

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div><dt class="text-sm text-slate-500">Waktu Mulai</dt><dd class="mt-1 font-medium text-slate-800">{{ formatDateTime(schedule.starts_at, schedule.timezone) }}</dd></div>
                <div v-if="schedule.ends_at"><dt class="text-sm text-slate-500">Waktu Selesai</dt><dd class="mt-1 font-medium text-slate-800">{{ formatDateTime(schedule.ends_at, schedule.timezone) }}</dd></div>
                <div><dt class="text-sm text-slate-500">Metode</dt><dd class="mt-1 font-medium text-slate-800">{{ scheduleMethodLabel[schedule.method] ?? schedule.method }}</dd></div>
                <div v-if="schedule.method === 'ON_SITE' && schedule.location"><dt class="text-sm text-slate-500">Lokasi</dt><dd class="mt-1 font-medium text-slate-800">{{ schedule.location }}</dd></div>
                <div v-if="schedule.method === 'ONLINE' && schedule.meeting_url"><dt class="text-sm text-slate-500">Tautan Pertemuan</dt><dd class="mt-1"><a :href="schedule.meeting_url" target="_blank" rel="noopener" class="font-medium text-[#0061a5] hover:underline">{{ schedule.meeting_url }}</a></dd></div>
            </dl>
            <p v-if="schedule.instructions" class="mt-4 whitespace-pre-line rounded-lg bg-slate-50 p-4 text-sm text-slate-700">{{ schedule.instructions }}</p>
        </section>

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-[#002045]">Riwayat</h2>
            <p v-if="!history.length" class="mt-3 text-sm text-slate-500">Belum ada riwayat perubahan.</p>
            <ol v-else class="mt-4 space-y-3 border-l-2 border-slate-200 pl-4">
                <li v-for="(event, index) in history" :key="index">
                    <p class="text-sm font-semibold text-slate-800">{{ scheduleEventLabel[event.event_type] ?? event.event_type }}</p>
                    <p class="text-xs text-slate-500">{{ formatDateTime(event.occurred_at) }}</p>
                </li>
            </ol>
        </section>
    </AppShell>
</template>
