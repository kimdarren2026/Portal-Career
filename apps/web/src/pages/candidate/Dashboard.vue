<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import {
    applicationStatusBadgeClass,
    applicationStatusLabel,
    formatDateTime,
    scheduleMethodLabel,
    scheduleStatusLabel,
    statusLabel,
} from '@/lib/labels'

interface RecentApplication {
    id: number
    application_code: string
    vacancy_id: number
    vacancy_title: string | null
    current_status: string
    first_applied_at: string | null
}

interface UpcomingSchedule {
    id: number
    selection_type: string
    starts_at: string | null
    timezone: string | null
    method: string
    status: string
    stage_label: string | null
}

const props = defineProps<{
    counts: { applications?: number; schedules_upcoming?: number }
    recent_applications: RecentApplication[]
    upcoming_schedules: UpcomingSchedule[]
}>()

const page = usePage<{ props: { auth?: { user?: { name?: string } | null } } }>()
const greetingName = computed(() => {
    const name = (page.props as any).auth?.user?.name?.trim()
    return name ? name.split(/\s+/)[0] : 'Kandidat'
})
</script>

<template>
    <Head title="Dashboard" />
    <AppShell persona="candidate" active="dashboard" title="Dashboard">
        <section class="overflow-hidden rounded-3xl bg-[radial-gradient(circle_at_top_right,_#2e9ce6,_transparent_42%),linear-gradient(135deg,_#062247,_#0b3769)] px-6 py-8 text-white shadow-[0_18px_45px_rgba(6,34,71,0.18)] sm:px-8 sm:py-10">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-200">Ruang kandidat</p>
            <div class="mt-4 flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Halo, {{ greetingName }}</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-blue-50 sm:text-base">Pantau perkembangan lamaran dan persiapkan agenda seleksi Anda dari satu tempat.</p>
                </div>
                <Link href="/lowongan" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white px-4 py-3 text-sm font-bold text-[#06305b] shadow-sm transition hover:bg-blue-50">Cari lowongan <span class="ml-2" aria-hidden="true">→</span></Link>
            </div>
        </section>

        <section class="mt-6 grid gap-4 sm:grid-cols-2" aria-label="Ringkasan aktivitas">
            <Link href="/lamaran-saya" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div><p class="text-sm font-semibold text-slate-600">Lamaran Saya</p><p class="mt-2 text-4xl font-bold tracking-tight text-[#002045]">{{ props.counts.applications ?? 0 }}</p></div>
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-blue-50 text-xl text-[#0061a5]" aria-hidden="true">◫</span>
                </div>
                <p class="mt-5 text-sm font-semibold text-[#0061a5] group-hover:underline">Lihat semua lamaran <span aria-hidden="true">→</span></p>
            </Link>
            <Link href="/jadwal-seleksi" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div><p class="text-sm font-semibold text-slate-600">Jadwal Mendatang</p><p class="mt-2 text-4xl font-bold tracking-tight text-[#002045]">{{ props.counts.schedules_upcoming ?? 0 }}</p></div>
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-sky-50 text-xl text-sky-700" aria-hidden="true">◷</span>
                </div>
                <p class="mt-5 text-sm font-semibold text-[#0061a5] group-hover:underline">Lihat jadwal seleksi <span aria-hidden="true">→</span></p>
            </Link>
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-2">
            <div class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-4"><div><p class="page-eyebrow">Aktivitas</p><h2 class="mt-1 text-xl font-bold text-[#002045]">Lamaran terbaru</h2></div><Link href="/lamaran-saya" class="text-sm font-semibold text-[#0061a5] hover:underline">Lihat semua</Link></div>
                <div v-if="!props.recent_applications.length" class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center">
                    <p class="font-semibold text-slate-700">Belum ada lamaran</p><p class="mt-1 text-sm text-slate-500">Mulai dari lowongan yang paling sesuai dengan minat Anda.</p>
                    <Link href="/lowongan" class="mt-4 inline-flex rounded-lg bg-[#0061a5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#004172]">Cari Lowongan</Link>
                </div>
                <ul v-else class="mt-5 divide-y divide-slate-100">
                    <li v-for="application in props.recent_applications" :key="application.id"><Link :href="`/lamaran-saya/${application.id}`" class="block py-4 first:pt-0 transition hover:rounded-lg hover:bg-slate-50 hover:px-3">
                        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate font-semibold text-slate-800">{{ application.vacancy_title ?? `Lowongan #${application.vacancy_id}` }}</p><p class="mt-1 text-sm text-slate-500">Diajukan {{ formatDateTime(application.first_applied_at) }}</p></div><span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold" :class="applicationStatusBadgeClass[application.current_status] ?? 'bg-slate-100 text-slate-700'">{{ statusLabel(applicationStatusLabel, application.current_status) }}</span></div>
                    </Link></li>
                </ul>
            </div>

            <div class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-4"><div><p class="page-eyebrow">Agenda</p><h2 class="mt-1 text-xl font-bold text-[#002045]">Seleksi berikutnya</h2></div><Link href="/jadwal-seleksi" class="text-sm font-semibold text-[#0061a5] hover:underline">Lihat semua</Link></div>
                <div v-if="!props.upcoming_schedules.length" class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center"><p class="font-semibold text-slate-700">Belum ada jadwal mendatang</p><p class="mt-1 text-sm text-slate-500">Jadwal dari perusahaan akan muncul otomatis di sini.</p></div>
                <ul v-else class="mt-5 divide-y divide-slate-100">
                    <li v-for="schedule in props.upcoming_schedules" :key="schedule.id"><Link :href="`/jadwal-seleksi/${schedule.id}`" class="block py-4 first:pt-0 transition hover:rounded-lg hover:bg-slate-50 hover:px-3">
                        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate font-semibold text-slate-800">{{ schedule.stage_label ?? schedule.selection_type }}</p><p class="mt-1 text-sm text-slate-500">{{ formatDateTime(schedule.starts_at, schedule.timezone) }} · {{ scheduleMethodLabel[schedule.method] ?? schedule.method }}</p></div><span class="shrink-0 rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-800">{{ statusLabel(scheduleStatusLabel, schedule.status) }}</span></div>
                    </Link></li>
                </ul>
            </div>
        </section>
    </AppShell>
</template>
