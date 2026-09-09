<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'

const featuredVacancies = [
    { title: 'Data Scientist', company: 'PT Teknologi Nusantara', location: 'Jakarta Selatan', type: 'Full-time', tone: 'company' },
    { title: 'Dosen Ilmu Komputer', company: 'Fakultas Teknik, Universitas', location: 'Kampus Utama', type: 'Kontrak', tone: 'campus' },
    { title: 'UI/UX Designer', company: 'Bank Nusantara', location: 'Jakarta Pusat', type: 'Magang', tone: 'company' },
]
</script>

<template>
    <Head title="Beranda" />

    <div class="min-h-screen bg-[#f6f8fc] text-[#181c1e]">
        <header class="sticky top-0 z-20 border-b border-slate-200/80 bg-white/90 backdrop-blur">
            <nav class="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 lg:px-12" aria-label="Navigasi utama">
                <Link href="/" class="text-xl font-bold tracking-tight text-[#002045]">Portal Karir Kampus</Link>
                <div class="hidden items-center gap-6 text-sm font-medium text-slate-600 lg:flex">
                    <Link href="/" class="text-[#0061a5]">Beranda</Link>
                    <Link href="/lowongan" class="transition hover:text-[#0061a5]">Cari Lowongan</Link>
                    <Link href="/lowongan?target_audience=FINAL_YEAR_AND_ALUMNI" class="transition hover:text-[#0061a5]">Karier untuk Alumni</Link>
                </div>
                <div class="flex items-center gap-3 text-sm font-semibold">
                    <Link href="/register" class="hidden text-slate-600 transition hover:text-[#0061a5] sm:block">Daftarkan Perusahaan</Link>
                    <Link href="/login" class="rounded-lg bg-[#002045] px-4 py-2.5 text-white transition hover:bg-[#1a365d]">Masuk / Daftar</Link>
                </div>
            </nav>
        </header>

        <main>
            <section class="relative isolate overflow-hidden bg-[radial-gradient(circle_at_50%_0%,_#dbeafe,_transparent_48%)] px-5 py-20 text-center lg:px-12 lg:py-28">
                <div class="absolute left-1/2 top-10 -z-10 h-72 w-72 -translate-x-1/2 rounded-full bg-blue-200/40 blur-3xl" aria-hidden="true" />
                <p class="mx-auto mb-5 w-fit rounded-full border border-blue-100 bg-white/85 px-4 py-2 text-sm font-semibold text-[#0061a5] shadow-sm">Portal karier resmi universitas</p>
                <h1 class="mx-auto max-w-4xl text-4xl font-bold tracking-tight text-[#002045] sm:text-5xl lg:text-6xl">Temukan Peluang Karier dan Bangun Masa Depan Anda</h1>
                <p class="mx-auto mt-6 max-w-2xl text-lg leading-8 text-slate-600">Temukan lowongan di kampus dan peluang dari mitra perusahaan terverifikasi—untuk mahasiswa tingkat akhir dan alumni.</p>
                <div class="mt-10 flex flex-col justify-center gap-3 sm:flex-row">
                    <Link href="/lowongan" class="rounded-lg bg-[#002045] px-7 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#1a365d]">Cari Lowongan</Link>
                    <Link href="/register" class="rounded-lg border border-[#0061a5] bg-white px-7 py-3.5 text-sm font-semibold text-[#0061a5] transition hover:bg-blue-50">Daftarkan Perusahaan</Link>
                </div>
                <dl class="mx-auto mt-14 grid max-w-2xl grid-cols-3 divide-x divide-slate-200 rounded-2xl border border-white/80 bg-white/75 px-3 py-4 shadow-sm backdrop-blur">
                    <div><dt class="text-xl font-bold text-[#002045]">1</dt><dd class="mt-1 text-xs font-medium text-slate-500">Portal resmi</dd></div>
                    <div><dt class="text-xl font-bold text-[#002045]">2</dt><dd class="mt-1 text-xs font-medium text-slate-500">Jalur karier</dd></div>
                    <div><dt class="text-xl font-bold text-[#002045]">24/7</dt><dd class="mt-1 text-xs font-medium text-slate-500">Akses lowongan</dd></div>
                </dl>
            </section>

            <section class="mx-auto grid max-w-7xl gap-6 px-5 pb-20 lg:grid-cols-2 lg:px-12">
                <Link href="/lowongan?vacancy_type=CAMPUS_EMPLOYMENT" class="group rounded-2xl border border-slate-200 bg-white p-8 shadow-[0_10px_30px_rgba(15,23,42,0.05)] transition hover:-translate-y-1 hover:border-blue-200 hover:shadow-xl">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-[#002045]/10 text-2xl text-[#002045]">⌂</div>
                    <h2 class="mt-6 text-2xl font-semibold text-[#002045]">Karier di Kampus</h2>
                    <p class="mt-3 leading-7 text-slate-600">Peluang sebagai dosen, tenaga kependidikan, dan staf administrasi di lingkungan universitas.</p>
                    <span class="mt-6 inline-block font-semibold text-[#0061a5] transition group-hover:translate-x-1">Lihat Peluang →</span>
                </Link>
                <Link href="/lowongan?target_audience=FINAL_YEAR_AND_ALUMNI" class="group rounded-2xl border border-slate-200 bg-white p-8 shadow-[0_10px_30px_rgba(15,23,42,0.05)] transition hover:-translate-y-1 hover:border-blue-200 hover:shadow-xl">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-50 text-2xl text-[#0061a5]">↗</div>
                    <h2 class="mt-6 text-2xl font-semibold text-[#002045]">Karier untuk Alumni</h2>
                    <p class="mt-3 leading-7 text-slate-600">Lowongan dan kesempatan karier dari perusahaan yang telah diverifikasi oleh Career Center.</p>
                    <span class="mt-6 inline-block font-semibold text-[#0061a5] transition group-hover:translate-x-1">Lihat Peluang →</span>
                </Link>
            </section>

            <section class="bg-slate-100 py-16">
                <div class="mx-auto max-w-7xl px-5 lg:px-12">
                    <div class="flex items-end justify-between gap-4">
                        <div><p class="text-sm font-semibold uppercase tracking-wider text-[#0061a5]">Pilihan terbaru</p><h2 class="mt-2 text-3xl font-bold text-[#002045]">Lowongan Terbaru</h2></div>
                        <Link href="/lowongan" class="hidden text-sm font-semibold text-[#0061a5] hover:underline sm:block">Lihat semua →</Link>
                    </div>
                    <div class="mt-8 grid gap-6 md:grid-cols-3">
                        <article v-for="vacancy in featuredVacancies" :key="vacancy.title" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="flex items-start justify-between gap-4"><div class="flex h-12 w-12 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 font-bold text-[#002045]">{{ vacancy.tone === 'campus' ? 'UNIV' : 'PK' }}</div><span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-[#0061a5]">{{ vacancy.type }}</span></div>
                            <h3 class="mt-6 text-lg font-semibold text-[#002045]">{{ vacancy.title }}</h3>
                            <p class="mt-1 text-sm font-medium text-slate-600">{{ vacancy.company }}</p>
                            <p class="mt-5 border-t border-slate-100 pt-4 text-sm text-slate-500">⌖ {{ vacancy.location }}</p>
                        </article>
                    </div>
                    <Link href="/lowongan" class="mt-7 block text-center text-sm font-semibold text-[#0061a5] sm:hidden">Lihat semua lowongan →</Link>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200 bg-white py-7 text-center text-sm text-slate-500">© {{ new Date().getFullYear() }} Portal Karir Kampus</footer>
    </div>
</template>
