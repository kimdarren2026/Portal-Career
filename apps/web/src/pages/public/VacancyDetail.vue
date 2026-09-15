<script setup lang="ts">
/**
 * Public vacancy detail (ADR-017 — Inertia SSR). Server-rendered content must
 * be present in the first response, not only after client hydration — no
 * client-only fetch populates this page. Mirrors
 * design/stitch/public/detail-lowongan's structure using only fields the
 * frozen public contract actually returns; salary, screening questions, the
 * raw external ATS URL, and "Lowongan Serupa" (unsourced) are intentionally
 * absent.
 */
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { employmentTypeLabel, statusLabel, vacancyTypeLabel, workplaceModeLabel } from '@/lib/labels'

interface CompanySummary {
    company_id?: number
    name?: string
    logo_url?: string | null
    industry_id?: number | null
    city_geographic_area_id?: number | null
    mitra_kampus_active?: boolean
}

interface Requirement {
    requirement_type: string
    education_level: string | null
    study_program_id: number | null
    skill_id: number | null
    minimum_years_experience: number | null
    value_text: string | null
    required: boolean
    sort_order: number
}

interface VacancyDetail {
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
    // Campus-owned vacancies have no associated company in the public API.
    company: CompanySummary | null
    description: string
    responsibilities: string | null
    openings_count: number
    minimum_education: string | null
    experience_requirement: string | null
    applies_externally: boolean
    requirements: Requirement[]
}

const props = defineProps<{ vacancy: VacancyDetail }>()

const audienceLabel: Record<string, string> = {
    PUBLIC: 'Publik',
    ALUMNI_ONLY: 'Alumni',
    FINAL_YEAR_AND_ALUMNI: 'Mahasiswa Tingkat Akhir & Alumni',
    INTERNAL: 'Internal',
}

const metaDescription = props.vacancy.description.slice(0, 160)

const publisherName = computed(() => {
    const name = props.vacancy.company?.name?.trim()

    if (name) return name

    return props.vacancy.vacancy_type === 'CAMPUS_EMPLOYMENT' ? 'Instansi Kampus' : 'Penerbit Lowongan'
})

const publisherSectionLabel = computed(() => props.vacancy.company?.name ? 'Tentang Perusahaan' : 'Tentang Instansi')

const CANDIDATE_ROLES = ['CANDIDATE_EXTERNAL', 'CANDIDATE_STUDENT_FINAL_YEAR', 'CANDIDATE_ALUMNI']
const page = usePage()
const isAuthenticated = computed(() => !!(page.props as any).auth?.user)
const isCandidate = computed(() => ((page.props as any).auth?.roles ?? []).some((r: string) => CANDIDATE_ROLES.includes(r)))
</script>

<template>
    <Head :title="`${vacancy.title} — ${publisherName}`">
        <meta name="description" :content="metaDescription" />
    </Head>
    <main class="min-h-screen bg-[#f6f8fc] py-8 sm:py-12">
        <div class="mx-auto max-w-5xl px-4 sm:px-6">
            <Link href="/lowongan" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 transition hover:text-[#0061a5]"><span aria-hidden="true">←</span> Kembali ke Cari Lowongan</Link>

            <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-3">
                <div class="md:col-span-2">
                    <div class="surface-card p-6 sm:p-8">
                        <p class="page-eyebrow">{{ statusLabel(vacancyTypeLabel, vacancy.vacancy_type) }}</p>
                        <h1 class="mt-3 text-3xl font-bold tracking-tight text-[#002045]">{{ vacancy.title }}</h1>
                        <p class="mt-1 text-slate-600">{{ publisherName }}<span v-if="vacancy.location"> · {{ vacancy.location }}</span></p>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            <span v-if="vacancy.employment_type" class="rounded-full bg-slate-100 px-2 py-1 text-slate-700">{{ statusLabel(employmentTypeLabel, vacancy.employment_type) }}</span>
                            <span v-if="vacancy.workplace_mode" class="rounded-full bg-slate-100 px-2 py-1 text-slate-700">{{ statusLabel(workplaceModeLabel, vacancy.workplace_mode) }}</span>
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-slate-700">{{ audienceLabel[vacancy.target_audience] ?? vacancy.target_audience }}</span>
                            <span v-if="vacancy.company?.mitra_kampus_active" class="rounded-full bg-emerald-100 px-2 py-1 text-emerald-800">Mitra Kampus</span>
                        </div>

                        <h2 class="mt-8 border-b border-slate-200 pb-3 text-lg font-bold text-[#002045]">Deskripsi Pekerjaan</h2>
                        <p class="mt-4 whitespace-pre-line leading-7 text-slate-700">{{ vacancy.description }}</p>

                        <template v-if="vacancy.responsibilities">
                            <h3 class="mt-8 text-base font-bold text-[#002045]">Tanggung Jawab Utama</h3>
                            <p class="mt-3 whitespace-pre-line leading-7 text-slate-700">{{ vacancy.responsibilities }}</p>
                        </template>

                        <template v-if="vacancy.requirements.length">
                            <h2 class="mt-8 border-b border-slate-200 pb-3 text-lg font-bold text-[#002045]">Kualifikasi</h2>
                            <ul class="mt-4 space-y-3 text-sm text-slate-700">
                                <li v-for="(req, i) in vacancy.requirements" :key="i">
                                    <span class="mr-2 text-[#0061a5]">✓</span><span v-if="req.education_level">Pendidikan: {{ req.education_level }}</span>
                                    <span v-else-if="req.minimum_years_experience !== null">Pengalaman minimal {{ req.minimum_years_experience }} tahun</span>
                                    <span v-else-if="req.value_text">{{ req.value_text }}</span>
                                    <span v-if="!req.required" class="text-slate-400"> (opsional)</span>
                                </li>
                            </ul>
                        </template>
                    </div>
                </div>

                <aside class="space-y-4">
                    <div class="surface-card p-5 sm:p-6">
                        <h3 class="text-base font-bold text-[#002045]">Informasi Lowongan</h3>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-slate-500">Jenis</dt><dd class="text-slate-800">{{ statusLabel(vacancyTypeLabel, vacancy.vacancy_type) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">Kebutuhan</dt><dd class="text-slate-800">{{ vacancy.openings_count }} orang</dd></div>
                            <div v-if="vacancy.minimum_education" class="flex justify-between"><dt class="text-slate-500">Pendidikan</dt><dd class="text-slate-800">{{ vacancy.minimum_education }}</dd></div>
                            <div v-if="vacancy.experience_requirement" class="flex justify-between"><dt class="text-slate-500">Pengalaman</dt><dd class="text-slate-800">{{ vacancy.experience_requirement }}</dd></div>
                        </dl>
                        <p v-if="vacancy.applies_externally" class="mt-4 rounded-md bg-slate-50 p-3 text-xs text-slate-600">
                            Lamaran untuk posisi ini diproses melalui situs perusahaan.
                        </p>
                        <p v-else class="mt-4 rounded-md bg-slate-50 p-3 text-xs text-slate-600">
                            Lamaran diproses melalui Portal Karir Kampus.
                        </p>

                        <template v-if="!vacancy.applies_externally">
                            <Link v-if="isAuthenticated && isCandidate" :href="`/lowongan/${vacancy.slug}/lamar`" class="mt-5 block rounded-xl bg-[#0061a5] px-4 py-3 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-[#004172]">
                                Lamar Sekarang
                            </Link>
                            <Link v-else-if="!isAuthenticated" href="/login" class="mt-5 block rounded-xl bg-[#0061a5] px-4 py-3 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-[#004172]">
                                Masuk untuk Melamar
                            </Link>
                        </template>
                    </div>

                    <div class="surface-card p-5 sm:p-6">
                        <h3 class="text-base font-bold text-[#002045]">{{ publisherSectionLabel }}</h3>
                        <p class="mt-2 text-sm text-slate-700">{{ publisherName }}</p>
                    </div>
                </aside>
            </div>
        </div>
    </main>
</template>
