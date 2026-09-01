<script setup lang="ts">
/**
 * Career Center Dashboard (Frontend Vertical Slice v9) — the FR-REP-002
 * subset that maps to a literal server-scoped count over data Career Center
 * already reads (CompanyScope / VacancyScope, both global readers). No risk
 * score, SLA, approval-rate or verification-performance metric is shown; the
 * FR-REP-002 items whose runtime is deferred (Mitra Kampus, alumni, external
 * apply, incomplete outcome) are omitted, not fabricated.
 */
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { companyStatusBadgeClass, companyStatusLabel, statusLabel, vacancyStatusBadgeClass, vacancyStatusLabel } from '@/lib/labels'

const props = defineProps<{
    counts: {
        verification_queue?: number
        company_revision_required?: number
        verified_companies?: number
        moderation_queue?: number
        vacancy_revision_required?: number
        published_vacancies?: number
    }
    companies_by_status?: Record<string, number>
    vacancies_by_status?: Record<string, number>
}>()

const companyRows = computed(() =>
    Object.entries(props.companies_by_status ?? {}).map(([status, count]) => ({ status, count })).sort((a, b) => b.count - a.count),
)
const vacancyRows = computed(() =>
    Object.entries(props.vacancies_by_status ?? {}).map(([status, count]) => ({ status, count })).sort((a, b) => b.count - a.count),
)
</script>

<template>
    <Head title="Dashboard" />
    <AppShell persona="career-center" active="dashboard" title="Dashboard">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Dashboard</h1>
        <p class="mt-2 max-w-2xl text-slate-600">Ringkasan antrean verifikasi perusahaan dan moderasi lowongan.</p>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link href="/verifikasi-perusahaan?status=PENDING_VERIFICATION" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-[#0061a5]">
                <p class="text-sm font-medium text-slate-600">Antrean Verifikasi Perusahaan</p>
                <p class="mt-2 text-3xl font-bold text-[#002045]">{{ counts.verification_queue ?? 0 }}</p>
            </Link>
            <Link href="/verifikasi-perusahaan?status=REVISION_REQUIRED" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-[#0061a5]">
                <p class="text-sm font-medium text-slate-600">Perusahaan Perlu Perbaikan</p>
                <p class="mt-2 text-3xl font-bold text-[#002045]">{{ counts.company_revision_required ?? 0 }}</p>
            </Link>
            <Link href="/data-perusahaan?status=VERIFIED" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-[#0061a5]">
                <p class="text-sm font-medium text-slate-600">Perusahaan Terverifikasi</p>
                <p class="mt-2 text-3xl font-bold text-[#002045]">{{ counts.verified_companies ?? 0 }}</p>
            </Link>
            <Link href="/moderasi-lowongan?status=PENDING_REVIEW" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-[#0061a5]">
                <p class="text-sm font-medium text-slate-600">Antrean Moderasi Lowongan</p>
                <p class="mt-2 text-3xl font-bold text-[#002045]">{{ counts.moderation_queue ?? 0 }}</p>
            </Link>
            <Link href="/moderasi-lowongan?status=REVISION_REQUIRED" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-[#0061a5]">
                <p class="text-sm font-medium text-slate-600">Lowongan Perlu Perbaikan</p>
                <p class="mt-2 text-3xl font-bold text-[#002045]">{{ counts.vacancy_revision_required ?? 0 }}</p>
            </Link>
            <Link href="/moderasi-lowongan?status=PUBLISHED" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-[#0061a5]">
                <p class="text-sm font-medium text-slate-600">Lowongan Dipublikasikan</p>
                <p class="mt-2 text-3xl font-bold text-[#002045]">{{ counts.published_vacancies ?? 0 }}</p>
            </Link>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-slate-600">Perusahaan per Status</p>
                    <Link href="/data-perusahaan" class="text-xs font-semibold text-[#0061a5] hover:underline">Data Perusahaan →</Link>
                </div>
                <ul class="mt-4 flex flex-wrap gap-2">
                    <li v-for="row in companyRows" :key="row.status">
                        <Link :href="`/data-perusahaan?status=${row.status}`" class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold"
                            :class="companyStatusBadgeClass[row.status] ?? 'bg-slate-100 text-slate-700'">
                            {{ statusLabel(companyStatusLabel, row.status) }}<span class="rounded-full bg-white/60 px-1.5">{{ row.count }}</span>
                        </Link>
                    </li>
                    <li v-if="companyRows.length === 0" class="text-sm text-slate-500">Belum ada data perusahaan.</li>
                </ul>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-slate-600">Lowongan per Status</p>
                    <Link href="/moderasi-lowongan" class="text-xs font-semibold text-[#0061a5] hover:underline">Moderasi Lowongan →</Link>
                </div>
                <ul class="mt-4 flex flex-wrap gap-2">
                    <li v-for="row in vacancyRows" :key="row.status">
                        <Link :href="`/moderasi-lowongan?status=${row.status}`" class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold"
                            :class="vacancyStatusBadgeClass[row.status] ?? 'bg-slate-100 text-slate-700'">
                            {{ statusLabel(vacancyStatusLabel, row.status) }}<span class="rounded-full bg-white/60 px-1.5">{{ row.count }}</span>
                        </Link>
                    </li>
                    <li v-if="vacancyRows.length === 0" class="text-sm text-slate-500">Belum ada lowongan perusahaan.</li>
                </ul>
            </div>
        </div>
    </AppShell>
</template>
