<script setup lang="ts">
/**
 * Recruiter Dashboard (Frontend Vertical Slice v5) — operational snapshot for
 * FR-REP-001. Every figure is a literal server-scoped count delivered by
 * DashboardPageController; this page performs no aggregation and derives no
 * rate, score or benchmark. Cards link into the existing recruiter workflows.
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
    company: { name: string; verification_status: string } | null
    counts: {
        applicants?: number
        schedules_upcoming?: number
        revision_requests?: number
        incomplete_outcomes?: number
    }
    vacancies_by_status?: Record<string, number>
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
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Dashboard</h1>
        <p class="mt-2 max-w-2xl text-slate-600">Ringkasan operasional rekrutmen pada perusahaan Anda.</p>

        <!-- Company verification status -->
        <div v-if="company" class="mt-6 flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <p class="text-sm font-medium text-slate-600">Status Verifikasi Perusahaan</p>
                <p class="mt-1 text-lg font-semibold text-[#002045]">{{ company.name }}</p>
            </div>
            <span
                class="rounded-full px-3 py-1 text-xs font-semibold"
                :class="companyStatusBadgeClass[company.verification_status] ?? 'bg-slate-100 text-slate-700'"
            >
                {{ statusLabel(companyStatusLabel, company.verification_status) }}
            </span>
            <Link href="/status-verifikasi" class="text-xs font-semibold text-[#0061a5] hover:underline">Lihat Status Verifikasi →</Link>
        </div>

        <!-- Operational counts -->
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Link href="/pelamar" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-[#0061a5]">
                <p class="text-sm font-medium text-slate-600">Total Pelamar</p>
                <p class="mt-2 text-3xl font-bold text-[#002045]">{{ counts.applicants ?? 0 }}</p>
                <p class="mt-1 text-sm text-[#0061a5]">Lihat daftar pelamar →</p>
            </Link>
            <Link href="/jadwal-seleksi" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-[#0061a5]">
                <p class="text-sm font-medium text-slate-600">Jadwal Seleksi Mendatang</p>
                <p class="mt-2 text-3xl font-bold text-[#002045]">{{ counts.schedules_upcoming ?? 0 }}</p>
                <p class="mt-1 text-sm text-[#0061a5]">Lihat jadwal seleksi →</p>
            </Link>
            <Link href="/kelola-lowongan?status=REVISION_REQUIRED" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-[#0061a5]">
                <p class="text-sm font-medium text-slate-600">Lowongan Perlu Perbaikan</p>
                <p class="mt-2 text-3xl font-bold text-[#002045]">{{ counts.revision_requests ?? 0 }}</p>
                <p class="mt-1 text-sm text-[#0061a5]">Tindak lanjuti revisi →</p>
            </Link>
            <Link href="/outcome-rekrutmen" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-[#0061a5]">
                <p class="text-sm font-medium text-slate-600">Outcome Belum Lengkap</p>
                <p class="mt-2 text-3xl font-bold text-[#002045]">{{ counts.incomplete_outcomes ?? 0 }}</p>
                <p class="mt-1 text-sm text-[#0061a5]">Lengkapi outcome →</p>
            </Link>
        </div>

        <!-- Vacancy by status -->
        <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-600">Lowongan per Status</p>
                <Link href="/kelola-lowongan" class="text-xs font-semibold text-[#0061a5] hover:underline">Kelola Lowongan →</Link>
            </div>
            <p v-if="totalVacancies === 0" class="mt-4 text-sm text-slate-500">Belum ada lowongan.</p>
            <ul v-else class="mt-4 flex flex-wrap gap-2">
                <li v-for="row in vacancyRows" :key="row.status">
                    <Link
                        :href="`/kelola-lowongan?status=${row.status}`"
                        class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold hover:ring-2 hover:ring-[#0061a5]/30"
                        :class="vacancyStatusBadgeClass[row.status] ?? 'bg-slate-100 text-slate-700'"
                    >
                        {{ statusLabel(vacancyStatusLabel, row.status) }}
                        <span class="rounded-full bg-white/60 px-1.5">{{ row.count }}</span>
                    </Link>
                </li>
            </ul>
        </div>
    </AppShell>
</template>
