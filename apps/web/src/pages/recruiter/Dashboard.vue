<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppShell from '@/layouts/AppShell.vue'

defineProps<{ counts: { applicants?: number; schedules_upcoming?: number } }>()
</script>

<template>
    <Head title="Dashboard" />
    <AppShell persona="recruiter" active="dashboard" title="Dashboard">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Dashboard</h1>
        <p class="mt-2 max-w-2xl text-slate-600">Ringkasan pelamar dan jadwal seleksi pada perusahaan Anda.</p>

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
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
        </div>
    </AppShell>
</template>
