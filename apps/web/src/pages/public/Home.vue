<script setup lang="ts">
/**
 * Public career-portal homepage (PGC-V1 / PD-G). Inertia SSR, crawlable.
 * The only dynamic content is `latest_vacancies` — real PUBLISHED vacancies
 * the server already decided to show (ListPublicVacancies). No fabricated
 * data, no metrics, no environment diagnostics.
 */
import { Head, Link } from '@inertiajs/vue3'
import { employmentTypeLabel, statusLabel, workplaceModeLabel } from '@/lib/labels'

interface CompanySummary {
    name: string
}

interface VacancyCard {
    slug: string
    title: string
    employment_type: string | null
    workplace_mode: string | null
    location: string | null
    company: CompanySummary
}

defineProps<{
    latest_vacancies: VacancyCard[]
}>()
</script>

<template>
    <Head title="Beranda" />

    <div class="min-h-screen bg-[#f6f8fc] text-[#181c1e]">
        <header class="border-b border-slate-200 bg-white">
            <nav class="mx-auto flex h-16 max-w-6xl items-center justify-between px-5" aria-label="Navigasi utama">
                <Link href="/" class="text-lg font-bold tracking-tight text-[#002045]">Portal Karir</Link>
                <div class="flex items-center gap-3 text-sm font-medium">
                    <Link href="/lowongan" class="text-slate-600 transition hover:text-[#0061a5]">Cari Lowongan</Link>
                    <Link href="/login" class="text-slate-600 transition hover:text-[#0061a5]">Masuk</Link>
                    <Link href="/register" class="rounded-lg bg-[#002045] px-4 py-2 font-semibold text-white transition hover:bg-[#1a365d]">Daftar</Link>
                </div>
            </nav>
        </header>

        <main class="mx-auto max-w-6xl px-5 pb-20">
            <!-- Hero -->
            <section class="py-16 text-center">
                <h1 class="mx-auto max-w-3xl text-4xl font-bold tracking-tight text-[#002045] sm:text-5xl">
                    Temukan Peluang Karier Terbaikmu
                </h1>
                <p class="mx-auto mt-5 max-w-2xl text-lg leading-8 text-slate-600">
                    Portal Karir STIKES Advaita Medika Tabanan menghubungkan mahasiswa, alumni, dan pencari kerja
                    dengan peluang karier di kampus maupun perusahaan terverifikasi.
                </p>
                <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                    <Link href="/lowongan" class="rounded-lg bg-[#002045] px-7 py-3 text-sm font-semibold text-white transition hover:bg-[#1a365d]">
                        Cari Lowongan
                    </Link>
                    <Link href="/register" class="rounded-lg border border-[#0061a5] bg-white px-7 py-3 text-sm font-semibold text-[#0061a5] transition hover:bg-blue-50">
                        Daftarkan Perusahaan
                    </Link>
                </div>
            </section>

            <!-- Quick pathways -->
            <section class="grid gap-5 sm:grid-cols-2">
                <Link
                    href="/lowongan?vacancy_type=CAMPUS_EMPLOYMENT"
                    class="rounded-2xl border border-slate-200 bg-white p-7 transition hover:border-blue-200 hover:shadow-md"
                >
                    <h2 class="text-xl font-semibold text-[#002045]">Karier di Kampus</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Peluang sebagai dosen, tenaga kependidikan, dan staf di lingkungan kampus.
                    </p>
                    <span class="mt-4 inline-block text-sm font-semibold text-[#0061a5]">Lihat peluang &rarr;</span>
                </Link>
                <Link
                    href="/lowongan?target_audience=FINAL_YEAR_AND_ALUMNI"
                    class="rounded-2xl border border-slate-200 bg-white p-7 transition hover:border-blue-200 hover:shadow-md"
                >
                    <h2 class="text-xl font-semibold text-[#002045]">Karier untuk Alumni</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Lowongan dari perusahaan yang telah diverifikasi oleh Career Center.
                    </p>
                    <span class="mt-4 inline-block text-sm font-semibold text-[#0061a5]">Lihat peluang &rarr;</span>
                </Link>
            </section>

            <!-- Latest vacancies -->
            <section class="mt-14">
                <div class="flex items-end justify-between">
                    <h2 class="text-2xl font-bold text-[#002045]">Lowongan Terbaru</h2>
                    <Link href="/lowongan" class="text-sm font-semibold text-[#0061a5] hover:underline">Lihat semua &rarr;</Link>
                </div>

                <p v-if="latest_vacancies.length === 0" class="mt-6 rounded-xl border border-slate-200 bg-white p-8 text-center text-slate-500">
                    Belum ada lowongan yang tersedia saat ini.
                </p>

                <div v-else class="mt-6 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="vacancy in latest_vacancies"
                        :key="vacancy.slug"
                        :href="`/lowongan/${vacancy.slug}`"
                        class="flex flex-col rounded-xl border border-slate-200 bg-white p-6 transition hover:border-blue-200 hover:shadow-md"
                    >
                        <h3 class="text-base font-semibold text-[#002045]">{{ vacancy.title }}</h3>
                        <p class="mt-1 text-sm font-medium text-slate-600">{{ vacancy.company.name }}</p>
                        <p class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-500">
                            <span v-if="vacancy.location">{{ vacancy.location }}</span>
                            <span v-if="vacancy.employment_type"> &middot; {{ statusLabel(employmentTypeLabel, vacancy.employment_type) }}</span>
                            <span v-if="vacancy.workplace_mode"> &middot; {{ statusLabel(workplaceModeLabel, vacancy.workplace_mode) }}</span>
                        </p>
                    </Link>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl flex-col gap-2 px-5 py-8 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                <span>Portal Karir STIKES Advaita Medika Tabanan</span>
                <Link href="/lowongan" class="font-medium text-[#0061a5] hover:underline">
                    Menemukan lowongan mencurigakan? Laporkan dari halaman lowongan terkait.
                </Link>
            </div>
        </footer>
    </div>
</template>
