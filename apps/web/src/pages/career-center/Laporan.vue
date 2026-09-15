<script setup lang="ts">
/**
 * Read-only Career Center report. Metrics are literal grouped counts supplied
 * by the server's existing company and company-vacancy scopes.
 */
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import {
    companyStatusBadgeClass,
    companyStatusLabel,
    statusLabel,
    vacancyStatusBadgeClass,
    vacancyStatusLabel,
} from '@/lib/labels'

const props = defineProps<{
    companies_by_status?: Record<string, number>
    vacancies_by_status?: Record<string, number>
}>()

type ReportRow = { status: string; count: number }

function rows(source: Record<string, number> | null | undefined): ReportRow[] {
    return Object.entries(source ?? {})
        .map(([status, count]) => ({ status, count }))
        .sort((a, b) => b.count - a.count)
}

function total(source: Record<string, number> | null | undefined): number {
    return Object.values(source ?? {}).reduce((sum, count) => sum + count, 0)
}

const companyRows = computed(() => rows(props.companies_by_status))
const vacancyRows = computed(() => rows(props.vacancies_by_status))
const verifiedCompanies = computed(() => props.companies_by_status?.VERIFIED ?? 0)
const publishedVacancies = computed(() => props.vacancies_by_status?.PUBLISHED ?? 0)
</script>

<template>
    <Head title="Laporan" />
    <AppShell persona="career-center" active="laporan" title="Laporan">
        <section class="overflow-hidden rounded-3xl bg-[radial-gradient(circle_at_top_right,_#2e9ce6,_transparent_42%),linear-gradient(135deg,_#062247,_#0b3769)] px-6 py-8 text-white shadow-[0_18px_45px_rgba(6,34,71,0.18)] sm:px-8 sm:py-10">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-200">Laporan Career Center</p>
            <div class="mt-4 flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
                <div class="max-w-2xl">
                    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Rekap perusahaan dan lowongan</h1>
                    <p class="mt-3 text-sm leading-6 text-blue-50 sm:text-base">Ringkasan terkini untuk pemantauan verifikasi perusahaan dan moderasi lowongan.</p>
                </div>
                <Link href="/dashboard" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white px-4 py-3 text-sm font-bold text-[#06305b] shadow-sm transition hover:bg-blue-50">
                    Kembali ke dashboard <span class="ml-2" aria-hidden="true">→</span>
                </Link>
            </div>
        </section>

        <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan laporan">
            <Link href="/data-perusahaan" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-[#0061a5]">
                <p class="text-sm font-semibold text-slate-600">Total Perusahaan</p>
                <p class="mt-2 text-4xl font-bold tracking-tight text-[#002045]">{{ total(companies_by_status) }}</p>
                <p class="mt-5 text-sm font-semibold text-[#0061a5]">Lihat data perusahaan →</p>
            </Link>
            <Link href="/data-perusahaan?status=VERIFIED" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-[#0061a5]">
                <p class="text-sm font-semibold text-slate-600">Perusahaan Terverifikasi</p>
                <p class="mt-2 text-4xl font-bold tracking-tight text-[#002045]">{{ verifiedCompanies }}</p>
                <p class="mt-5 text-sm font-semibold text-[#0061a5]">Tinjau perusahaan →</p>
            </Link>
            <Link href="/moderasi-lowongan" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-[#0061a5]">
                <p class="text-sm font-semibold text-slate-600">Total Lowongan</p>
                <p class="mt-2 text-4xl font-bold tracking-tight text-[#002045]">{{ total(vacancies_by_status) }}</p>
                <p class="mt-5 text-sm font-semibold text-[#0061a5]">Lihat semua lowongan →</p>
            </Link>
            <Link href="/moderasi-lowongan?status=PUBLISHED" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-[#0061a5]">
                <p class="text-sm font-semibold text-slate-600">Lowongan Dipublikasikan</p>
                <p class="mt-2 text-4xl font-bold tracking-tight text-[#002045]">{{ publishedVacancies }}</p>
                <p class="mt-5 text-sm font-semibold text-[#0061a5]">Tinjau lowongan →</p>
            </Link>
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="page-eyebrow">Perusahaan</p>
                        <h2 class="mt-1 text-xl font-bold text-[#002045]">Status verifikasi perusahaan</h2>
                    </div>
                    <Link href="/data-perusahaan" class="text-sm font-semibold text-[#0061a5] hover:underline">Lihat data</Link>
                </div>
                <p v-if="companyRows.length === 0" class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500">Belum ada data perusahaan untuk direkap.</p>
                <ul v-else class="mt-5 space-y-3">
                    <li v-for="row in companyRows" :key="row.status" class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="companyStatusBadgeClass[row.status] ?? 'bg-slate-100 text-slate-700'">{{ statusLabel(companyStatusLabel, row.status) }}</span>
                        <span class="text-2xl font-bold tracking-tight text-[#002045]">{{ row.count }}</span>
                    </li>
                </ul>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="page-eyebrow">Lowongan perusahaan</p>
                        <h2 class="mt-1 text-xl font-bold text-[#002045]">Status moderasi lowongan</h2>
                    </div>
                    <Link href="/moderasi-lowongan" class="text-sm font-semibold text-[#0061a5] hover:underline">Lihat lowongan</Link>
                </div>
                <p v-if="vacancyRows.length === 0" class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500">Belum ada lowongan perusahaan untuk direkap.</p>
                <ul v-else class="mt-5 space-y-3">
                    <li v-for="row in vacancyRows" :key="row.status" class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="vacancyStatusBadgeClass[row.status] ?? 'bg-slate-100 text-slate-700'">{{ statusLabel(vacancyStatusLabel, row.status) }}</span>
                        <span class="text-2xl font-bold tracking-tight text-[#002045]">{{ row.count }}</span>
                    </li>
                </ul>
            </article>
        </section>
    </AppShell>
</template>
