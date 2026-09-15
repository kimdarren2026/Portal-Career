<script setup lang="ts">
/**
 * Public vacancy listing (ADR-017 — Inertia SSR, crawlable first response).
 * All visibility/filter/sort logic lives server-side
 * (PublicVacancyScope / ListPublicVacancies) — this page only renders what
 * the server already decided to show. Not a Stitch pixel reproduction;
 * mirrors design/stitch/public/daftar-lowongan's structure (filter panel +
 * card list) using the frozen filter vocabulary only.
 */
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'
import { employmentTypeLabel, statusLabel, vacancyTypeLabel, workplaceModeLabel } from '@/lib/labels'

interface CompanySummary {
    company_id?: number
    name?: string
    logo_url?: string | null
    industry_id?: number | null
    city_geographic_area_id?: number | null
    mitra_kampus_active?: boolean
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
    // Campus-owned vacancies have no company by design. The public API
    // represents that as an empty object, so the page must not assume a
    // company name exists for every public result.
    company: CompanySummary | null
}

interface Pagination {
    page: number
    per_page: number
    total: number
    last_page: number
}

interface ReferenceItem {
    id: number
    name: string
    area_type?: 'PROVINCE' | 'CITY'
    parent_geographic_area_id?: number | null
}

const props = defineProps<{
    items: VacancyCard[]
    pagination: Pagination
    filters: Record<string, string>
    sort: string
    direction: string
    reference_data: {
        geographic_areas: ReferenceItem[]
        industries: ReferenceItem[]
        study_programs: ReferenceItem[]
    }
}>()

const form = reactive({
    q: props.filters.q ?? '',
    vacancy_type: props.filters.vacancy_type ?? '',
    employment_type: props.filters.employment_type ?? '',
    target_audience: props.filters.target_audience ?? '',
    workplace_mode: props.filters.workplace_mode ?? '',
    province_geographic_area_id: props.filters.province_geographic_area_id ?? '',
    city_geographic_area_id: props.filters.city_geographic_area_id ?? '',
    industry_id: props.filters.industry_id ?? '',
    study_program_id: props.filters.study_program_id ?? '',
})

const provinces = computed(() => props.reference_data.geographic_areas.filter((area) => area.area_type === 'PROVINCE'))
const cities = computed(() => props.reference_data.geographic_areas.filter((area) =>
    area.area_type === 'CITY' && (!form.province_geographic_area_id || String(area.parent_geographic_area_id) === form.province_geographic_area_id),
))

const audienceLabel: Record<string, string> = {
    PUBLIC: 'Publik',
    ALUMNI_ONLY: 'Alumni',
    FINAL_YEAR_AND_ALUMNI: 'Mahasiswa Tingkat Akhir & Alumni',
    INTERNAL: 'Internal',
}

function publisherName(item: VacancyCard): string {
    const name = item.company?.name?.trim()

    if (name) return name

    return item.vacancy_type === 'CAMPUS_EMPLOYMENT' ? 'Instansi Kampus' : 'Penerbit Lowongan'
}

function publisherInitials(item: VacancyCard): string {
    return publisherName(item).slice(0, 2).toUpperCase()
}

function queryFromForm(page?: number) {
    const query: Record<string, string> = {}
    for (const [key, value] of Object.entries(form)) {
        if (value) query[key] = value
    }
    if (page) query.page = String(page)
    return query
}

function applyFilters() {
    const query = queryFromForm()
    router.get('/lowongan', query, { preserveState: true, replace: true })
}

function goToPage(page: number) {
    router.get('/lowongan', queryFromForm(page), { preserveState: true, replace: true })
}

function resetFilters() {
    Object.assign(form, {
        q: '', vacancy_type: '', employment_type: '', target_audience: '', workplace_mode: '',
        province_geographic_area_id: '', city_geographic_area_id: '', industry_id: '', study_program_id: '',
    })
    router.get('/lowongan', {}, { preserveState: true, replace: true })
}
</script>

<template>
    <Head title="Cari Lowongan" />
    <main class="min-h-screen bg-[#f6f8fc] py-8 sm:py-12">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <section class="overflow-hidden rounded-3xl bg-[radial-gradient(circle_at_top_right,_#2485ce,_transparent_48%),linear-gradient(135deg,_#062247,_#0b3769)] px-6 py-9 text-white shadow-[0_18px_45px_rgba(6,34,71,0.2)] sm:px-10">
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-blue-200">Eksplorasi peluang</p>
                <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Cari Lowongan</h1>
                <p class="mt-3 max-w-2xl leading-7 text-blue-50">Temukan lowongan resmi kampus dan perusahaan yang telah diverifikasi Career Center.</p>
            </section>

            <form class="surface-card mt-6 grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 sm:p-6 lg:grid-cols-4" @submit.prevent="applyFilters">
                <div class="sm:col-span-2 lg:col-span-4"><p class="page-eyebrow">Saring lowongan</p><p class="mt-1 text-sm text-slate-500">Pilih kriteria yang paling relevan untuk Anda.</p></div>
                <input v-model="form.q" type="search" placeholder="Cari judul lowongan..." class="rounded-md border-slate-300 text-sm sm:col-span-2" />
                <select v-model="form.vacancy_type" class="rounded-md border-slate-300 text-sm">
                    <option value="">Semua Jalur Karier</option>
                    <option v-for="(label, code) in vacancyTypeLabel" :key="code" :value="code">{{ label }}</option>
                </select>
                <select v-model="form.employment_type" class="rounded-md border-slate-300 text-sm">
                    <option value="">Semua Jenis Kerja</option>
                    <option v-for="(label, code) in employmentTypeLabel" :key="code" :value="code">{{ label }}</option>
                </select>
                <select v-model="form.target_audience" class="rounded-md border-slate-300 text-sm">
                    <option value="">Semua Target</option>
                    <option value="PUBLIC">Publik</option>
                    <option value="ALUMNI_ONLY">Alumni</option>
                    <option value="FINAL_YEAR_AND_ALUMNI">Mahasiswa Tingkat Akhir & Alumni</option>
                </select>
                <select v-model="form.workplace_mode" class="rounded-md border-slate-300 text-sm">
                    <option value="">Semua Sistem Kerja</option>
                    <option v-for="(label, code) in workplaceModeLabel" :key="code" :value="code">{{ label }}</option>
                </select>
                <select v-model="form.province_geographic_area_id" class="rounded-md border-slate-300 text-sm" @change="form.city_geographic_area_id = ''">
                    <option value="">Semua Provinsi</option>
                    <option v-for="area in provinces" :key="area.id" :value="String(area.id)">{{ area.name }}</option>
                </select>
                <select v-model="form.city_geographic_area_id" class="rounded-md border-slate-300 text-sm">
                    <option value="">Semua Kota/Kabupaten</option>
                    <option v-for="area in cities" :key="area.id" :value="String(area.id)">{{ area.name }}</option>
                </select>
                <select v-model="form.industry_id" class="rounded-md border-slate-300 text-sm">
                    <option value="">Semua Industri</option>
                    <option v-for="industry in reference_data.industries" :key="industry.id" :value="String(industry.id)">{{ industry.name }}</option>
                </select>
                <select v-model="form.study_program_id" class="rounded-md border-slate-300 text-sm">
                    <option value="">Semua Program Studi</option>
                    <option v-for="program in reference_data.study_programs" :key="program.id" :value="String(program.id)">{{ program.name }}</option>
                </select>
                <div class="flex gap-2 sm:col-span-2 lg:col-span-4">
                    <button type="submit" class="rounded-lg bg-[#0061a5] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#004172]">Terapkan Filter</button>
                    <button type="button" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-100" @click="resetFilters">Reset</button>
                </div>
            </form>

            <div class="mt-8 flex items-center justify-between gap-4"><div><p class="page-eyebrow">Hasil pencarian</p><p class="mt-1 text-sm text-slate-500">{{ pagination.total }} lowongan ditemukan.</p></div><Link href="/" class="hidden text-sm font-semibold text-[#0061a5] hover:underline sm:block">← Kembali ke beranda</Link></div>

            <ul class="mt-4 grid gap-4 md:grid-cols-2">
                <li v-for="item in items" :key="item.slug" class="group surface-card overflow-hidden transition duration-200 hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-lg">
                    <Link :href="`/lowongan/${item.slug}`" class="block p-5 sm:p-6">
                        <div class="flex items-start justify-between gap-4"><div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#002045]/8 text-sm font-bold text-[#002045]">{{ publisherInitials(item) }}</div><span v-if="item.company?.mitra_kampus_active" class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Mitra Kampus</span></div>
                        <h2 class="mt-5 text-lg font-bold text-[#002045] transition group-hover:text-[#0061a5]">{{ item.title }}</h2>
                        <p class="mt-1 text-sm font-medium text-slate-600">{{ publisherName(item) }}</p>
                        <p v-if="item.location" class="mt-4 text-sm text-slate-500">⌖ {{ item.location }}</p>
                        <div class="mt-4 flex flex-wrap gap-2 text-xs">
                            <span v-if="item.employment_type" class="rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-700">{{ statusLabel(employmentTypeLabel, item.employment_type) }}</span>
                            <span v-if="item.workplace_mode" class="rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-700">{{ statusLabel(workplaceModeLabel, item.workplace_mode) }}</span>
                            <span class="rounded-full bg-blue-50 px-2.5 py-1 font-medium text-[#0061a5]">{{ audienceLabel[item.target_audience] ?? item.target_audience }}</span>
                        </div>
                        <p class="mt-5 border-t border-slate-100 pt-4 text-sm font-semibold text-[#0061a5]">Lihat detail <span aria-hidden="true">→</span></p>
                    </Link>
                </li>
            </ul>

            <p v-if="items.length === 0" class="surface-card mt-6 border-dashed p-10 text-center text-sm text-slate-500">
                Tidak ada lowongan yang sesuai. Coba kurangi kriteria atau reset filter Anda.
            </p>

            <nav v-if="pagination.last_page > 1" class="surface-card mt-8 flex items-center justify-center gap-2 p-3 text-sm">
                <button type="button" :disabled="pagination.page <= 1" class="rounded-lg border border-slate-300 px-3 py-2 font-semibold disabled:opacity-40" @click="goToPage(pagination.page - 1)">Sebelumnya</button>
                <span class="text-slate-600">Halaman {{ pagination.page }} dari {{ pagination.last_page }}</span>
                <button type="button" :disabled="pagination.page >= pagination.last_page" class="rounded-lg border border-slate-300 px-3 py-2 font-semibold disabled:opacity-40" @click="goToPage(pagination.page + 1)">Berikutnya</button>
            </nav>
        </div>
    </main>
</template>
