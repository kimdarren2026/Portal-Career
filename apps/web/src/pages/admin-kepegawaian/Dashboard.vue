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
        <section class="overflow-hidden rounded-3xl bg-[radial-gradient(circle_at_top_right,_#2e9ce6,_transparent_42%),linear-gradient(135deg,_#062247,_#0b3769)] px-6 py-8 text-white shadow-[0_18px_45px_rgba(6,34,71,0.18)] sm:px-8 sm:py-10">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-200">Back office kepegawaian</p>
            <div class="mt-4 flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
                <div class="max-w-2xl">
                    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Rekrutmen kampus, lebih terarah</h1>
                    <p class="mt-3 text-sm leading-6 text-blue-50 sm:text-base">Pantau lowongan kampus, kandidat, dan tahapan seleksi dari satu ruang kerja operasional.</p>
                </div>
                <Link href="/kepegawaian/lowongan-kampus/baru" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white px-4 py-3 text-sm font-bold text-[#06305b] shadow-sm transition hover:bg-blue-50">Buat lowongan kampus <span class="ml-2" aria-hidden="true">+</span></Link>
            </div>
        </section>

        <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan operasional">
            <Link href="/kepegawaian/pelamar" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md">
                <div class="flex items-start justify-between gap-3"><div><p class="text-sm font-semibold text-slate-600">Total Pelamar</p><p class="mt-2 text-4xl font-bold tracking-tight text-[#002045]">{{ counts.applicants ?? 0 }}</p></div><span class="grid h-10 w-10 place-items-center rounded-xl bg-blue-50 text-lg text-[#0061a5]" aria-hidden="true">◉</span></div>
                <p class="mt-5 text-sm font-semibold text-[#0061a5] group-hover:underline">Lihat pelamar <span aria-hidden="true">→</span></p>
            </Link>
            <Link href="/kepegawaian/jadwal-seleksi" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-sky-200 hover:shadow-md">
                <div class="flex items-start justify-between gap-3"><div><p class="text-sm font-semibold text-slate-600">Jadwal Mendatang</p><p class="mt-2 text-4xl font-bold tracking-tight text-[#002045]">{{ counts.schedules_upcoming ?? 0 }}</p></div><span class="grid h-10 w-10 place-items-center rounded-xl bg-sky-50 text-lg text-sky-700" aria-hidden="true">◷</span></div>
                <p class="mt-5 text-sm font-semibold text-[#0061a5] group-hover:underline">Lihat jadwal <span aria-hidden="true">→</span></p>
            </Link>
            <Link href="/kepegawaian/offering" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-violet-200 hover:shadow-md">
                <div class="flex items-start justify-between gap-3"><div><p class="text-sm font-semibold text-slate-600">Offering Aktif</p><p class="mt-2 text-4xl font-bold tracking-tight text-[#002045]">{{ counts.offers_active ?? 0 }}</p></div><span class="grid h-10 w-10 place-items-center rounded-xl bg-violet-50 text-lg text-violet-700" aria-hidden="true">↗</span></div>
                <p class="mt-5 text-sm font-semibold text-[#0061a5] group-hover:underline">Kelola offering <span aria-hidden="true">→</span></p>
            </Link>
            <Link href="/kepegawaian/outcome-rekrutmen" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-amber-200 hover:shadow-md">
                <div class="flex items-start justify-between gap-3"><div><p class="text-sm font-semibold text-slate-600">Outcome Belum Lengkap</p><p class="mt-2 text-4xl font-bold tracking-tight text-[#002045]">{{ counts.incomplete_outcomes ?? 0 }}</p></div><span class="grid h-10 w-10 place-items-center rounded-xl bg-amber-50 text-lg text-amber-700" aria-hidden="true">✓</span></div>
                <p class="mt-5 text-sm font-semibold text-[#0061a5] group-hover:underline">Lengkapi outcome <span aria-hidden="true">→</span></p>
            </Link>
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(19rem,0.85fr)]">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div><p class="page-eyebrow">Portofolio kampus</p><h2 class="mt-1 text-xl font-bold text-[#002045]">Status lowongan kampus</h2></div>
                    <Link href="/kepegawaian/lowongan-kampus" class="text-sm font-semibold text-[#0061a5] hover:underline">Kelola lowongan <span aria-hidden="true">→</span></Link>
                </div>
                <div v-if="totalVacancies === 0" class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center">
                    <p class="font-semibold text-slate-700">Belum ada lowongan kampus</p>
                    <p class="mt-1 text-sm text-slate-500">Mulai rekrutmen dengan membuat lowongan pertama.</p>
                    <Link href="/kepegawaian/lowongan-kampus/baru" class="mt-4 inline-flex rounded-lg bg-[#0061a5] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#004172]">Buat Lowongan</Link>
                </div>
                <ul v-else class="mt-5 grid gap-3 sm:grid-cols-2">
                    <li v-for="row in rows" :key="row.status">
                        <Link :href="`/kepegawaian/lowongan-kampus?status=${row.status}`" class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3 transition hover:border-blue-200 hover:bg-blue-50/50">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="vacancyStatusBadgeClass[row.status] ?? 'bg-slate-100 text-slate-700'">{{ statusLabel(vacancyStatusLabel, row.status) }}</span>
                            <span class="text-2xl font-bold tracking-tight text-[#002045]">{{ row.count }}</span>
                        </Link>
                    </li>
                </ul>
            </div>

            <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <p class="page-eyebrow">Tindakan cepat</p>
                <h2 class="mt-1 text-xl font-bold text-[#002045]">Mulai dari sini</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">Akses langsung ke pekerjaan rekrutmen yang paling sering digunakan.</p>
                <div class="mt-5 space-y-3">
                    <Link href="/kepegawaian/lowongan-kampus/baru" class="block rounded-xl bg-[#0061a5] px-4 py-3 text-sm font-bold text-white transition hover:bg-[#004172]">+ Buat lowongan kampus</Link>
                    <Link href="/kepegawaian/pelamar" class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50">Tinjau pelamar <span class="text-[#0061a5]" aria-hidden="true">→</span></Link>
                    <Link href="/kepegawaian/jadwal-seleksi" class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50">Atur jadwal seleksi <span class="text-[#0061a5]" aria-hidden="true">→</span></Link>
                    <Link href="/kepegawaian/outcome-rekrutmen" class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50">Catat outcome rekrutmen <span class="text-[#0061a5]" aria-hidden="true">→</span></Link>
                </div>
            </aside>
        </section>
    </AppShell>
</template>
