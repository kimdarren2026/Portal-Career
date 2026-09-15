<script setup lang="ts">
/**
 * Recruiter Dashboard (Frontend Vertical Slice v5) — operational snapshot for
 * FR-REP-001. Every figure and activity row is delivered from a server-scoped
 * query by DashboardPageController; this page performs no aggregation and
 * derives no rate, score or benchmark. Cards link into live workflows only.
 */
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import {
    applicationStatusBadgeClass,
    applicationStatusLabel,
    companyStatusBadgeClass,
    companyStatusLabel,
    formatDateTime,
    statusLabel,
    vacancyStatusBadgeClass,
    vacancyStatusLabel,
} from '@/lib/labels'

const props = defineProps<{
    company: { name: string; verification_status: string } | null
    counts: {
        applicants?: number
        schedules_upcoming?: number
        revision_requests?: number
        incomplete_outcomes?: number
    }
    vacancies_by_status?: Record<string, number>
    priority_vacancies: Array<{
        id: number
        title: string
        current_status: string
        created_at: string | null
    }>
    recent_applicants: Array<{
        id: number
        vacancy_id: number
        vacancy_title: string | null
        current_status: string
        first_applied_at: string | null
        candidate: { name?: string | null; headline?: string | null }
    }>
}>()

const vacancyRows = computed(() =>
    Object.entries(props.vacancies_by_status ?? {})
        .map(([status, count]) => ({ status, count }))
        .sort((a, b) => b.count - a.count),
)
const totalVacancies = computed(() => vacancyRows.value.reduce((sum, r) => sum + r.count, 0))
</script>

<template>
    <Head title="Dashboard" />
    <AppShell persona="recruiter" active="dashboard" title="Dashboard">
        <section class="overflow-hidden rounded-3xl bg-[radial-gradient(circle_at_top_right,_#2485ce,_transparent_45%),linear-gradient(135deg,_#062247,_#0b3769)] px-6 py-8 text-white shadow-[0_18px_45px_rgba(6,34,71,0.18)] sm:px-8 sm:py-10">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-200">Ruang kerja recruiter</p>
            <div class="mt-4 flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ company?.name ?? 'Dashboard Recruiter' }}</h1>
                    <div v-if="company" class="mt-3 flex flex-wrap items-center gap-3 text-sm text-blue-50"><span>Verifikasi perusahaan</span><span class="rounded-full px-3 py-1 text-xs font-bold" :class="companyStatusBadgeClass[company.verification_status] ?? 'bg-white/15 text-white'">{{ statusLabel(companyStatusLabel, company.verification_status) }}</span><Link href="/status-verifikasi" class="font-semibold text-white underline-offset-4 hover:underline">Lihat status</Link></div>
                    <p v-else class="mt-3 text-sm text-blue-50">Kelola lowongan, pelamar, dan proses seleksi dari satu tempat.</p>
                </div>
                <Link href="/kelola-lowongan/baru" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white px-4 py-3 text-sm font-bold text-[#06305b] shadow-sm transition hover:bg-blue-50">Tambah lowongan <span class="ml-2" aria-hidden="true">+</span></Link>
            </div>
        </section>

        <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan rekrutmen">
            <Link href="/pelamar" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md"><div class="flex items-start justify-between gap-3"><div><p class="text-sm font-semibold text-slate-600">Total Pelamar</p><p class="mt-2 text-4xl font-bold tracking-tight text-[#002045]">{{ counts.applicants ?? 0 }}</p></div><span class="grid h-10 w-10 place-items-center rounded-xl bg-blue-50 text-lg text-[#0061a5]" aria-hidden="true">◉</span></div><p class="mt-5 text-sm font-semibold text-[#0061a5] group-hover:underline">Lihat pelamar <span aria-hidden="true">→</span></p></Link>
            <Link href="/jadwal-seleksi" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md"><div class="flex items-start justify-between gap-3"><div><p class="text-sm font-semibold text-slate-600">Jadwal Mendatang</p><p class="mt-2 text-4xl font-bold tracking-tight text-[#002045]">{{ counts.schedules_upcoming ?? 0 }}</p></div><span class="grid h-10 w-10 place-items-center rounded-xl bg-sky-50 text-lg text-sky-700" aria-hidden="true">◷</span></div><p class="mt-5 text-sm font-semibold text-[#0061a5] group-hover:underline">Lihat jadwal <span aria-hidden="true">→</span></p></Link>
            <Link href="/kelola-lowongan?status=REVISION_REQUIRED" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-red-200 hover:shadow-md"><div class="flex items-start justify-between gap-3"><div><p class="text-sm font-semibold text-slate-600">Perlu Perbaikan</p><p class="mt-2 text-4xl font-bold tracking-tight text-[#002045]">{{ counts.revision_requests ?? 0 }}</p></div><span class="grid h-10 w-10 place-items-center rounded-xl bg-red-50 text-lg text-red-700" aria-hidden="true">!</span></div><p class="mt-5 text-sm font-semibold text-[#0061a5] group-hover:underline">Tindak lanjuti <span aria-hidden="true">→</span></p></Link>
            <Link href="/outcome-rekrutmen" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md"><div class="flex items-start justify-between gap-3"><div><p class="text-sm font-semibold text-slate-600">Outcome Belum Lengkap</p><p class="mt-2 text-4xl font-bold tracking-tight text-[#002045]">{{ counts.incomplete_outcomes ?? 0 }}</p></div><span class="grid h-10 w-10 place-items-center rounded-xl bg-indigo-50 text-lg text-indigo-700" aria-hidden="true">✓</span></div><p class="mt-5 text-sm font-semibold text-[#0061a5] group-hover:underline">Lengkapi outcome <span aria-hidden="true">→</span></p></Link>
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-2">
            <div class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><div class="flex flex-wrap items-center justify-between gap-4"><div><p class="page-eyebrow">Prioritas</p><h2 class="mt-1 text-xl font-bold text-[#002045]">Lowongan perlu perbaikan</h2></div><Link href="/kelola-lowongan?status=REVISION_REQUIRED" class="text-sm font-semibold text-[#0061a5] hover:underline">Kelola</Link></div>
                <div v-if="!priority_vacancies.length" class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center"><p class="font-semibold text-slate-700">Tidak ada revisi yang menunggu</p><p class="mt-1 text-sm text-slate-500">Lowongan yang perlu ditindaklanjuti akan muncul di sini.</p></div>
                <ul v-else class="mt-5 divide-y divide-slate-100"><li v-for="vacancy in priority_vacancies" :key="vacancy.id"><Link :href="`/kelola-lowongan/${vacancy.id}`" class="block py-4 first:pt-0 transition hover:rounded-lg hover:bg-slate-50 hover:px-3"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate font-semibold text-slate-800">{{ vacancy.title }}</p><p class="mt-1 text-sm text-slate-500">Dibuat {{ formatDateTime(vacancy.created_at) }}</p></div><span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold" :class="vacancyStatusBadgeClass[vacancy.current_status] ?? 'bg-slate-100 text-slate-700'">{{ statusLabel(vacancyStatusLabel, vacancy.current_status) }}</span></div></Link></li></ul>
            </div>

            <div class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><div class="flex flex-wrap items-center justify-between gap-4"><div><p class="page-eyebrow">Aktivitas</p><h2 class="mt-1 text-xl font-bold text-[#002045]">Pelamar terbaru</h2></div><Link href="/pelamar" class="text-sm font-semibold text-[#0061a5] hover:underline">Lihat semua</Link></div>
                <div v-if="!recent_applicants.length" class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center"><p class="font-semibold text-slate-700">Belum ada pelamar</p><p class="mt-1 text-sm text-slate-500">Lamaran baru untuk lowongan perusahaan akan tampil di sini.</p></div>
                <ul v-else class="mt-5 divide-y divide-slate-100"><li v-for="application in recent_applicants" :key="application.id"><Link :href="`/pelamar/${application.id}`" class="block py-4 first:pt-0 transition hover:rounded-lg hover:bg-slate-50 hover:px-3"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate font-semibold text-slate-800">{{ application.candidate.name ?? 'Kandidat' }}</p><p class="mt-1 truncate text-sm text-slate-500">{{ application.vacancy_title ?? `Lowongan #${application.vacancy_id}` }} · {{ formatDateTime(application.first_applied_at) }}</p></div><span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold" :class="applicationStatusBadgeClass[application.current_status] ?? 'bg-slate-100 text-slate-700'">{{ statusLabel(applicationStatusLabel, application.current_status) }}</span></div></Link></li></ul>
            </div>
        </section>

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><div class="flex flex-wrap items-center justify-between gap-4"><div><p class="page-eyebrow">Portofolio lowongan</p><h2 class="mt-1 text-xl font-bold text-[#002045]">Status lowongan</h2></div><Link href="/kelola-lowongan" class="text-sm font-semibold text-[#0061a5] hover:underline">Kelola lowongan <span aria-hidden="true">→</span></Link></div>
            <p v-if="totalVacancies === 0" class="mt-5 text-sm text-slate-500">Belum ada lowongan. Tambahkan lowongan pertama untuk mulai merekrut.</p>
            <ul v-else class="mt-5 flex flex-wrap gap-2"><li v-for="row in vacancyRows" :key="row.status"><Link :href="`/kelola-lowongan?status=${row.status}`" class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold transition hover:ring-2 hover:ring-[#0061a5]/30" :class="vacancyStatusBadgeClass[row.status] ?? 'bg-slate-100 text-slate-700'">{{ statusLabel(vacancyStatusLabel, row.status) }} <span class="rounded-full bg-white/60 px-1.5">{{ row.count }}</span></Link></li></ul>
        </section>
    </AppShell>
</template>
