<script setup lang="ts">
/** Admin Kepegawaian Dashboard (Campus Recruitment Frontend v8) — FR-REP-003
 *  operational snapshot. Literal server-scoped counts only; no analytics. */
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { statusLabel, vacancyStatusBadgeClass, vacancyStatusLabel } from '@/lib/labels'

const props = defineProps<{
    counts: { applicants?: number; schedules_upcoming?: number; offers_active?: number; incomplete_outcomes?: number }
    vacancies_by_status?: Record<string, number>
}>()

const rows = computed(() =>
    Object.entries(props.vacancies_by_status ?? {}).map(([status, count]) => ({ status, count })).sort((a, b) => b.count - a.count),
)
const totalVacancies = computed(() => rows.value.reduce((s, r) => s + r.count, 0))
</script>

<template>
    <Head title="Dashboard" />
    <AppShell persona="admin-kepegawaian" active="kepegawaian/dashboard" title="Dashboard">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Dashboard</h1>
        <p class="mt-2 max-w-2xl text-slate-600">Ringkasan operasional rekrutmen Karier di Kampus.</p>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Link href="/kepegawaian/pelamar" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-[#0061a5]">
                <p class="text-sm font-medium text-slate-600">Total Pelamar</p>
                <p class="mt-2 text-3xl font-bold text-[#002045]">{{ counts.applicants ?? 0 }}</p>
            </Link>
            <Link href="/kepegawaian/jadwal-seleksi" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-[#0061a5]">
                <p class="text-sm font-medium text-slate-600">Jadwal Seleksi Mendatang</p>
                <p class="mt-2 text-3xl font-bold text-[#002045]">{{ counts.schedules_upcoming ?? 0 }}</p>
            </Link>
            <Link href="/kepegawaian/offering" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-[#0061a5]">
                <p class="text-sm font-medium text-slate-600">Offering</p>
                <p class="mt-2 text-3xl font-bold text-[#002045]">{{ counts.offers_active ?? 0 }}</p>
            </Link>
            <Link href="/kepegawaian/outcome-rekrutmen" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-[#0061a5]">
                <p class="text-sm font-medium text-slate-600">Outcome Belum Lengkap</p>
                <p class="mt-2 text-3xl font-bold text-[#002045]">{{ counts.incomplete_outcomes ?? 0 }}</p>
            </Link>
        </div>

        <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-600">Lowongan Kampus per Status</p>
                <Link href="/kepegawaian/lowongan-kampus" class="text-xs font-semibold text-[#0061a5] hover:underline">Kelola Lowongan Kampus →</Link>
            </div>
            <p v-if="totalVacancies === 0" class="mt-4 text-sm text-slate-500">Belum ada lowongan kampus.</p>
            <ul v-else class="mt-4 flex flex-wrap gap-2">
                <li v-for="row in rows" :key="row.status">
                    <Link :href="`/kepegawaian/lowongan-kampus?status=${row.status}`"
                        class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold hover:ring-2 hover:ring-[#0061a5]/30"
                        :class="vacancyStatusBadgeClass[row.status] ?? 'bg-slate-100 text-slate-700'">
                        {{ statusLabel(vacancyStatusLabel, row.status) }}
                        <span class="rounded-full bg-white/60 px-1.5">{{ row.count }}</span>
                    </Link>
                </li>
            </ul>
        </div>
    </AppShell>
</template>
