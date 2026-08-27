<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppShell from '@/layouts/AppShell.vue'

defineProps<{ counts: { applications?: number; schedules_upcoming?: number } }>()
</script>

<template>
    <Head title="Dashboard" />
    <AppShell persona="candidate" active="dashboard" title="Dashboard">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Dashboard</h1>
        <p class="mt-2 max-w-2xl text-slate-600">Ringkasan aktivitas lamaran dan jadwal seleksi Anda.</p>

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            <Link href="/lamaran-saya" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-[#0061a5]">
                <p class="text-sm font-medium text-slate-600">Lamaran Saya</p>
                <p class="mt-2 text-3xl font-bold text-[#002045]">{{ counts.applications ?? 0 }}</p>
                <p class="mt-1 text-sm text-[#0061a5]">Lihat semua lamaran →</p>
            </Link>
            <Link href="/jadwal-seleksi" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-[#0061a5]">
                <p class="text-sm font-medium text-slate-600">Jadwal Seleksi Mendatang</p>
                <p class="mt-2 text-3xl font-bold text-[#002045]">{{ counts.schedules_upcoming ?? 0 }}</p>
                <p class="mt-1 text-sm text-[#0061a5]">Lihat jadwal seleksi →</p>
            </Link>
        </div>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-[#002045]">Mulai cari peluang baru</h2>
            <p class="mt-2 text-sm text-slate-600">Jelajahi lowongan yang telah diverifikasi dan dimoderasi Career Center.</p>
            <Link href="/lowongan" class="mt-4 inline-block rounded-lg bg-[#0061a5] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#004172]">Cari Lowongan</Link>
        </div>
    </AppShell>
</template>
