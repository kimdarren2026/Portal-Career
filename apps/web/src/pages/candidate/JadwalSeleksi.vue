<script setup lang="ts">
/** Read-only candidate schedule list. Mirrors design/stitch/candidate/jadwal-seleksi's structure. */
import { Head, Link } from '@inertiajs/vue3'
import AppShell from '@/layouts/AppShell.vue'
import { formatDateTime, scheduleMethodLabel, scheduleStatusLabel, statusLabel } from '@/lib/labels'

interface ScheduleRow {
    id: number
    application_id: number
    selection_type: string
    starts_at: string | null
    timezone: string | null
    method: string
    location: string | null
    meeting_url: string | null
    status: string
    stage_label: string | null
}

defineProps<{ items: ScheduleRow[]; pagination: { page: number; last_page: number } }>()
</script>

<template>
    <Head title="Jadwal Seleksi" />
    <AppShell persona="candidate" active="jadwal-seleksi" title="Jadwal Seleksi">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Jadwal Seleksi</h1>
        <p class="mt-2 max-w-2xl text-slate-600">Seluruh jadwal seleksi yang telah ditetapkan untuk lamaran Anda.</p>

        <div v-if="!items.length" class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
            <p class="text-lg font-semibold text-slate-700">Belum ada jadwal seleksi</p>
            <p class="mt-2 text-sm text-slate-500">Jadwal yang ditetapkan oleh perusahaan akan muncul di sini.</p>
        </div>

        <ul v-else class="mt-6 space-y-3">
            <li v-for="item in items" :key="item.id">
                <Link :href="`/jadwal-seleksi/${item.id}`" class="block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-[#0061a5]">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-slate-800">{{ item.stage_label ?? item.selection_type }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ formatDateTime(item.starts_at, item.timezone) }} · {{ scheduleMethodLabel[item.method] ?? item.method }}</p>
                            <p v-if="item.method === 'ON_SITE' && item.location" class="mt-1 text-sm text-slate-500">Lokasi: {{ item.location }}</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ statusLabel(scheduleStatusLabel, item.status) }}</span>
                    </div>
                </Link>
            </li>
        </ul>
    </AppShell>
</template>
