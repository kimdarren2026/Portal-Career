<script setup lang="ts">
/**
 * Read-only report for the campus HR workspace. Each section displays literal
 * grouped counts supplied by HrPageController's campus-scoped queries; this
 * page intentionally does not compute a rate, ranking, or performance score.
 */
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import {
    applicationStatusBadgeClass,
    applicationStatusLabel,
    scheduleStatusLabel,
    statusLabel,
    vacancyStatusBadgeClass,
    vacancyStatusLabel,
} from '@/lib/labels'

const props = defineProps<{
    vacancies_by_status: Record<string, number>
    applications_by_status: Record<string, number>
    schedules_by_status: Record<string, number>
    offers_by_status: Record<string, number>
    outcomes_by_type: Record<string, number>
}>()

const offerLabel: Record<string, string> = {
    DRAFT: 'Draf',
    SENT: 'Terkirim',
    PENDING_RESPONSE: 'Menunggu Respons',
    ACCEPTED: 'Diterima',
    REJECTED: 'Ditolak',
    EXPIRED: 'Kedaluwarsa',
}

const offerBadge: Record<string, string> = {
    DRAFT: 'bg-slate-100 text-slate-700',
    SENT: 'bg-indigo-100 text-indigo-800',
    PENDING_RESPONSE: 'bg-amber-100 text-amber-800',
    ACCEPTED: 'bg-green-100 text-green-800',
    REJECTED: 'bg-red-100 text-red-800',
    EXPIRED: 'bg-slate-200 text-slate-700',
}

function entries(source: Record<string, number> | null | undefined) {
    return Object.entries(source ?? {}).map(([status, count]) => ({ status, count })).sort((a, b) => b.count - a.count)
}

function sum(source: Record<string, number> | null | undefined): number {
    return Object.values(source ?? {}).reduce((total, count) => total + count, 0)
}

const vacancies = computed(() => entries(props.vacancies_by_status))
const applications = computed(() => entries(props.applications_by_status))
const schedules = computed(() => entries(props.schedules_by_status))
const offers = computed(() => entries(props.offers_by_status))
const outcomes = computed(() => entries(props.outcomes_by_type))
</script>

<template>
    <Head title="Laporan" />
    <AppShell persona="admin-kepegawaian" active="kepegawaian/laporan" title="Laporan">
        <section class="overflow-hidden rounded-3xl bg-[radial-gradient(circle_at_top_right,_#2e9ce6,_transparent_42%),linear-gradient(135deg,_#062247,_#0b3769)] px-6 py-8 text-white shadow-[0_18px_45px_rgba(6,34,71,0.18)] sm:px-8 sm:py-10">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-200">Laporan operasional</p>
            <div class="mt-4 flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
                <div class="max-w-2xl">
                    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Rekap rekrutmen kampus</h1>
                    <p class="mt-3 text-sm leading-6 text-blue-50 sm:text-base">Ringkasan langsung dari data lowongan, pelamar, jadwal, offering, dan outcome kampus.</p>
                </div>
                <Link href="/kepegawaian/dashboard" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white px-4 py-3 text-sm font-bold text-[#06305b] shadow-sm transition hover:bg-blue-50">Kembali ke dashboard <span class="ml-2" aria-hidden="true">→</span></Link>
            </div>
        </section>

        <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan laporan">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-slate-600">Lowongan Kampus</p><p class="mt-2 text-4xl font-bold tracking-tight text-[#002045]">{{ sum(vacancies_by_status) }}</p><Link href="/kepegawaian/lowongan-kampus" class="mt-5 inline-block text-sm font-semibold text-[#0061a5] hover:underline">Lihat lowongan →</Link></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-slate-600">Pelamar</p><p class="mt-2 text-4xl font-bold tracking-tight text-[#002045]">{{ sum(applications_by_status) }}</p><Link href="/kepegawaian/pelamar" class="mt-5 inline-block text-sm font-semibold text-[#0061a5] hover:underline">Lihat pelamar →</Link></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-slate-600">Jadwal Seleksi</p><p class="mt-2 text-4xl font-bold tracking-tight text-[#002045]">{{ sum(schedules_by_status) }}</p><Link href="/kepegawaian/jadwal-seleksi" class="mt-5 inline-block text-sm font-semibold text-[#0061a5] hover:underline">Lihat jadwal →</Link></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-slate-600">Outcome Tercatat</p><p class="mt-2 text-4xl font-bold tracking-tight text-[#002045]">{{ sum(outcomes_by_type) }}</p><Link href="/kepegawaian/outcome-rekrutmen" class="mt-5 inline-block text-sm font-semibold text-[#0061a5] hover:underline">Lihat outcome →</Link></div>
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-2">
            <article class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-4"><div><p class="page-eyebrow">Lowongan</p><h2 class="mt-1 text-xl font-bold text-[#002045]">Status lowongan kampus</h2></div><Link href="/kepegawaian/lowongan-kampus" class="text-sm font-semibold text-[#0061a5] hover:underline">Kelola</Link></div>
                <p v-if="!vacancies.length" class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500">Belum ada lowongan kampus untuk direkap.</p>
                <ul v-else class="mt-5 space-y-3"><li v-for="item in vacancies" :key="item.status" class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="vacancyStatusBadgeClass[item.status] ?? 'bg-slate-100 text-slate-700'">{{ statusLabel(vacancyStatusLabel, item.status) }}</span><span class="text-2xl font-bold tracking-tight text-[#002045]">{{ item.count }}</span></li></ul>
            </article>

            <article class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-4"><div><p class="page-eyebrow">Kandidat</p><h2 class="mt-1 text-xl font-bold text-[#002045]">Status pelamar</h2></div><Link href="/kepegawaian/pelamar" class="text-sm font-semibold text-[#0061a5] hover:underline">Lihat pelamar</Link></div>
                <p v-if="!applications.length" class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500">Belum ada pelamar kampus untuk direkap.</p>
                <ul v-else class="mt-5 space-y-3"><li v-for="item in applications" :key="item.status" class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="applicationStatusBadgeClass[item.status] ?? 'bg-slate-100 text-slate-700'">{{ statusLabel(applicationStatusLabel, item.status) }}</span><span class="text-2xl font-bold tracking-tight text-[#002045]">{{ item.count }}</span></li></ul>
            </article>

            <article class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-4"><div><p class="page-eyebrow">Seleksi</p><h2 class="mt-1 text-xl font-bold text-[#002045]">Status jadwal seleksi</h2></div><Link href="/kepegawaian/jadwal-seleksi" class="text-sm font-semibold text-[#0061a5] hover:underline">Lihat jadwal</Link></div>
                <p v-if="!schedules.length" class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500">Belum ada jadwal seleksi untuk direkap.</p>
                <ul v-else class="mt-5 space-y-3"><li v-for="item in schedules" :key="item.status" class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3"><span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-800">{{ statusLabel(scheduleStatusLabel, item.status) }}</span><span class="text-2xl font-bold tracking-tight text-[#002045]">{{ item.count }}</span></li></ul>
            </article>

            <article class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-4"><div><p class="page-eyebrow">Offering dan outcome</p><h2 class="mt-1 text-xl font-bold text-[#002045]">Keputusan rekrutmen</h2></div><Link href="/kepegawaian/offering" class="text-sm font-semibold text-[#0061a5] hover:underline">Lihat offering</Link></div>
                <div v-if="!offers.length && !outcomes.length" class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500">Belum ada offering atau outcome untuk direkap.</div>
                <div v-else class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Offering</p><ul v-if="offers.length" class="mt-3 space-y-2"><li v-for="item in offers" :key="item.status" class="flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="offerBadge[item.status] ?? 'bg-slate-100 text-slate-700'">{{ statusLabel(offerLabel, item.status) }}</span><span class="font-bold text-[#002045]">{{ item.count }}</span></li></ul><p v-else class="mt-3 text-sm text-slate-500">Belum ada offering.</p></div>
                    <div><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Outcome</p><ul v-if="outcomes.length" class="mt-3 space-y-2"><li v-for="item in outcomes" :key="item.status" class="flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="applicationStatusBadgeClass[item.status] ?? 'bg-slate-100 text-slate-700'">{{ statusLabel(applicationStatusLabel, item.status) }}</span><span class="font-bold text-[#002045]">{{ item.count }}</span></li></ul><p v-else class="mt-3 text-sm text-slate-500">Belum ada outcome.</p></div>
                </div>
            </article>
        </section>
    </AppShell>
</template>
