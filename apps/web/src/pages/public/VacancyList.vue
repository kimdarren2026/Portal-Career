<script setup lang="ts">
/**
 * Public vacancy listing (ADR-017 — Inertia SSR, crawlable first response).
 * All visibility/filter/sort logic lives server-side
 * (PublicVacancyScope / ListPublicVacancies) — this page only renders what
 * the server already decided to show. Not a Stitch pixel reproduction;
 * mirrors design/stitch/public/daftar-lowongan's structure (filter panel +
 * card list) using the frozen filter vocabulary only.
 */
import { Head, router } from '@inertiajs/vue3'
import { reactive } from 'vue'
import { employmentTypeLabel, statusLabel, workplaceModeLabel } from '@/lib/labels'

interface CompanySummary {
    company_id: number
    name: string
    logo_url: string | null
    industry_id: number | null
    city_geographic_area_id: number | null
    mitra_kampus_active: boolean
}

interface VacancyCard {
    slug: string
    title: string
    vacancy_type: string
    employment_type: string | null
    workplace_mode: string | null
    location: string | null
    target_audience: string
    application_method: string
    published_at: string | null
    close_at: string | null
    company: CompanySummary
}

interface Pagination {
    page: number
    per_page: number
    total: number
    last_page: number
}

const props = defineProps<{
    items: VacancyCard[]
    pagination: Pagination
    filters: Record<string, string>
    sort: string
    direction: string
}>()

const form = reactive({
    q: props.filters.q ?? '',
    vacancy_type: props.filters.vacancy_type ?? '',
    target_audience: props.filters.target_audience ?? '',
    workplace_mode: props.filters.workplace_mode ?? '',
})

const audienceLabel: Record<string, string> = {
    PUBLIC: 'Publik',
    ALUMNI_ONLY: 'Alumni',
    FINAL_YEAR_AND_ALUMNI: 'Mahasiswa Tingkat Akhir & Alumni',
    INTERNAL: 'Internal',
}

function applyFilters() {
    const query: Record<string, string> = {}
    for (const [key, value] of Object.entries(form)) {
        if (value) query[key] = value
    }
    router.get('/lowongan', query, { preserveState: true, replace: true })
}

function goToPage(page: number) {
    router.get('/lowongan', { ...form, page }, { preserveState: true, replace: true })
}
</script>

<template>
    <Head title="Cari Lowongan" />
    <main class="min-h-screen bg-slate-50 py-10">
        <div class="mx-auto max-w-5xl px-4">
            <h1 class="text-2xl font-semibold text-slate-900">Cari Lowongan</h1>
            <p class="mt-1 text-sm text-slate-600">Lowongan resmi yang telah terverifikasi dan dimoderasi Career Center.</p>

            <form class="mt-6 grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-4" @submit.prevent="applyFilters">
                <input v-model="form.q" type="search" placeholder="Cari judul lowongan..." class="rounded-md border-slate-300 text-sm sm:col-span-2" />
                <select v-model="form.target_audience" class="rounded-md border-slate-300 text-sm">
                    <option value="">Semua Target</option>
                    <option value="PUBLIC">Publik</option>
                    <option value="ALUMNI_ONLY">Alumni</option>
                    <option value="FINAL_YEAR_AND_ALUMNI">Mahasiswa Tingkat Akhir & Alumni</option>
                </select>
                <select v-model="form.workplace_mode" class="rounded-md border-slate-300 text-sm">
                    <option value="">Semua Sistem Kerja</option>
                    <option value="ONSITE">On-site</option>
                    <option value="HYBRID">Hybrid</option>
                    <option value="REMOTE">Remote</option>
                </select>
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white sm:col-span-4 sm:w-auto sm:justify-self-start">Terapkan Filter</button>
            </form>

            <p class="mt-6 text-sm text-slate-500">{{ pagination.total }} lowongan ditemukan.</p>

            <ul class="mt-3 space-y-3">
                <li v-for="item in items" :key="item.slug" class="rounded-lg border border-slate-200 bg-white p-5 hover:border-slate-300">
                    <a :href="`/lowongan/${item.slug}`" class="block">
                        <h2 class="text-lg font-semibold text-slate-900">{{ item.title }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ item.company.name }}<span v-if="item.location"> · {{ item.location }}</span></p>
                        <div class="mt-2 flex flex-wrap gap-2 text-xs">
                            <span v-if="item.employment_type" class="rounded-full bg-slate-100 px-2 py-1 text-slate-700">{{ statusLabel(employmentTypeLabel, item.employment_type) }}</span>
                            <span v-if="item.workplace_mode" class="rounded-full bg-slate-100 px-2 py-1 text-slate-700">{{ statusLabel(workplaceModeLabel, item.workplace_mode) }}</span>
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-slate-700">{{ audienceLabel[item.target_audience] ?? item.target_audience }}</span>
                            <span v-if="item.company.mitra_kampus_active" class="rounded-full bg-emerald-100 px-2 py-1 text-emerald-800">Mitra Kampus</span>
                        </div>
                    </a>
                </li>
            </ul>

            <p v-if="items.length === 0" class="mt-6 rounded-lg border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">
                Tidak ada lowongan yang sesuai dengan filter saat ini.
            </p>

            <nav v-if="pagination.last_page > 1" class="mt-8 flex items-center justify-center gap-2 text-sm">
                <button type="button" :disabled="pagination.page <= 1" class="rounded-md border border-slate-300 px-3 py-1.5 disabled:opacity-40" @click="goToPage(pagination.page - 1)">Sebelumnya</button>
                <span class="text-slate-600">Halaman {{ pagination.page }} dari {{ pagination.last_page }}</span>
                <button type="button" :disabled="pagination.page >= pagination.last_page" class="rounded-md border border-slate-300 px-3 py-1.5 disabled:opacity-40" @click="goToPage(pagination.page + 1)">Berikutnya</button>
            </nav>
        </div>
    </main>
</template>
