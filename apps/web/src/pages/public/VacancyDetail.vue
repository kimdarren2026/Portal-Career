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
import { computed, ref } from 'vue'
import { authRequest, errorText, newIdempotencyKey } from '@/lib/auth'
import { employmentTypeLabel, statusLabel, vacancyTypeLabel, workplaceModeLabel } from '@/lib/labels'

interface CompanySummary {
    company_id: number
    name: string
    logo_url: string | null
    industry_id: number | null
    city_geographic_area_id: number | null
    mitra_kampus_active: boolean
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
    id?: number
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
    description: string
    responsibilities: string | null
    openings_count: number
    minimum_education: string | null
    experience_requirement: string | null
    applies_externally: boolean
    requirements: Requirement[]
}

const props = defineProps<{
    vacancy: VacancyDetail
    external_apply?: { consent_version: string } | null
}>()

const audienceLabel: Record<string, string> = {
    PUBLIC: 'Publik',
    ALUMNI_ONLY: 'Alumni',
    FINAL_YEAR_AND_ALUMNI: 'Mahasiswa Tingkat Akhir & Alumni',
    INTERNAL: 'Internal',
}

const metaDescription = props.vacancy.description.slice(0, 160)

const CANDIDATE_ROLES = ['CANDIDATE_EXTERNAL', 'CANDIDATE_STUDENT_FINAL_YEAR', 'CANDIDATE_ALUMNI']
const page = usePage()
const isAuthenticated = computed(() => !!(page.props as any).auth?.user)
const isCandidate = computed(() => ((page.props as any).auth?.roles ?? []).some((r: string) => CANDIDATE_ROLES.includes(r)))

// External Apply — a candidate leaving for the company ATS. The click always
// flows through the tracked `external-apply/start` runtime: the server
// validates candidate/vacancy/eligibility, records an external_apply_event,
// and returns the ONLY destination we ever navigate to (the stored vacancy
// URL). A client-supplied URL is never sent or honoured.
const showExternalModal = ref(false)
const externalSubmitting = ref(false)
const externalError = ref('')

function openExternalModal(): void {
    externalError.value = ''
    showExternalModal.value = true
}

function closeExternalModal(): void {
    if (externalSubmitting.value) return
    showExternalModal.value = false
}

async function confirmExternalApply(): Promise<void> {
    const vacancyId = props.vacancy.id
    const version = props.external_apply?.consent_version
    if (!vacancyId || !version) {
        externalError.value = 'Alur lamaran eksternal tidak tersedia untuk lowongan ini.'
        return
    }
    externalSubmitting.value = true
    externalError.value = ''
    try {
        const { response, payload } = await authRequest(
            `/vacancies/${vacancyId}/external-apply/start`,
            { consent: { consent_version: version, accepted: true } },
            'POST',
            { 'Idempotency-Key': newIdempotencyKey() },
        )
        const destination = (payload.data as Record<string, unknown> | undefined)?.destination_url
        if (response.ok && typeof destination === 'string' && destination.length > 0) {
            window.location.href = destination
            return
        }
        if (response.status === 401) {
            window.location.href = '/login'
            return
        }
        externalError.value = errorText(payload)
    } catch {
        externalError.value = 'Permintaan tidak dapat diproses. Silakan coba kembali.'
    } finally {
        externalSubmitting.value = false
    }
}
</script>

<template>
    <Head :title="`${vacancy.title} — ${vacancy.company.name}`">
        <meta name="description" :content="metaDescription" />
    </Head>
    <main class="min-h-screen bg-slate-50 py-10">
        <div class="mx-auto max-w-4xl px-4">
            <a href="/lowongan" class="text-sm font-medium text-slate-600 hover:underline">&larr; Kembali ke Cari Lowongan</a>

            <div class="mt-4 grid grid-cols-1 gap-6 md:grid-cols-3">
                <div class="md:col-span-2">
                    <div class="rounded-lg border border-slate-200 bg-white p-6">
                        <h1 class="text-2xl font-semibold text-slate-900">{{ vacancy.title }}</h1>
                        <p class="mt-1 text-slate-600">{{ vacancy.company.name }}<span v-if="vacancy.location"> · {{ vacancy.location }}</span></p>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            <span v-if="vacancy.employment_type" class="rounded-full bg-slate-100 px-2 py-1 text-slate-700">{{ statusLabel(employmentTypeLabel, vacancy.employment_type) }}</span>
                            <span v-if="vacancy.workplace_mode" class="rounded-full bg-slate-100 px-2 py-1 text-slate-700">{{ statusLabel(workplaceModeLabel, vacancy.workplace_mode) }}</span>
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-slate-700">{{ audienceLabel[vacancy.target_audience] ?? vacancy.target_audience }}</span>
                            <span v-if="vacancy.company.mitra_kampus_active" class="rounded-full bg-emerald-100 px-2 py-1 text-emerald-800">Mitra Kampus</span>
                        </div>

                        <h2 class="mt-6 border-b border-slate-200 pb-2 text-lg font-semibold text-slate-900">Deskripsi Pekerjaan</h2>
                        <p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ vacancy.description }}</p>

                        <template v-if="vacancy.responsibilities">
                            <h3 class="mt-6 text-base font-semibold text-slate-900">Tanggung Jawab Utama</h3>
                            <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ vacancy.responsibilities }}</p>
                        </template>

                        <template v-if="vacancy.requirements.length">
                            <h2 class="mt-6 border-b border-slate-200 pb-2 text-lg font-semibold text-slate-900">Kualifikasi</h2>
                            <ul class="mt-3 list-inside list-disc space-y-1 text-sm text-slate-700">
                                <li v-for="(req, i) in vacancy.requirements" :key="i">
                                    <span v-if="req.education_level">Pendidikan: {{ req.education_level }}</span>
                                    <span v-else-if="req.minimum_years_experience !== null">Pengalaman minimal {{ req.minimum_years_experience }} tahun</span>
                                    <span v-else-if="req.value_text">{{ req.value_text }}</span>
                                    <span v-if="!req.required" class="text-slate-400"> (opsional)</span>
                                </li>
                            </ul>
                        </template>
                    </div>
                </div>

                <aside class="space-y-4">
                    <div class="rounded-lg border border-slate-200 bg-white p-5">
                        <h3 class="text-sm font-semibold text-slate-900">Informasi Lowongan</h3>
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
                            <Link v-if="isAuthenticated && isCandidate" :href="`/lowongan/${vacancy.slug}/lamar`" class="mt-4 block rounded-lg bg-[#0061a5] px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-[#004172]">
                                Lamar Sekarang
                            </Link>
                            <a v-else-if="!isAuthenticated" href="/login" class="mt-4 block rounded-lg bg-[#0061a5] px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-[#004172]">
                                Masuk untuk Melamar
                            </a>
                        </template>

                        <template v-else>
                            <button v-if="isAuthenticated && isCandidate" type="button" data-testid="external-apply-cta" class="mt-4 block w-full rounded-lg bg-[#0061a5] px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-[#004172]" @click="openExternalModal">
                                Lamar di Situs Perusahaan
                            </button>
                            <a v-else-if="!isAuthenticated" href="/login" class="mt-4 block rounded-lg bg-[#0061a5] px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-[#004172]">
                                Masuk untuk Melamar
                            </a>
                        </template>

                        <a :href="`/lowongan/${vacancy.slug}/laporkan`" class="mt-3 block text-center text-xs font-medium text-slate-500 hover:text-[#93000a] hover:underline">
                            Laporkan lowongan ini
                        </a>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-5">
                        <h3 class="text-sm font-semibold text-slate-900">Tentang Perusahaan</h3>
                        <p class="mt-2 text-sm text-slate-700">{{ vacancy.company.name }}</p>
                    </div>
                </aside>
            </div>
        </div>

        <div v-if="showExternalModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4" role="dialog" aria-modal="true" aria-labelledby="external-apply-title" @click.self="closeExternalModal">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h2 id="external-apply-title" class="text-lg font-semibold text-slate-900">Lanjut ke Situs Perusahaan?</h2>
                <p class="mt-3 text-sm text-slate-700">
                    Proses lamaran untuk lowongan ini dilakukan melalui situs perusahaan.
                    Aktivitas Anda akan dicatat di Portal Karir sebelum Anda melanjutkan.
                </p>
                <p v-if="externalError" class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-[#93000a]" role="alert" data-testid="external-apply-error">{{ externalError }}</p>
                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60" :disabled="externalSubmitting" @click="closeExternalModal">
                        Batal
                    </button>
                    <button type="button" data-testid="external-apply-confirm" class="rounded-lg bg-[#0061a5] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#004172] disabled:opacity-60" :disabled="externalSubmitting" @click="confirmExternalApply">
                        {{ externalSubmitting ? 'Memproses…' : 'Lanjutkan' }}
                    </button>
                </div>
            </div>
        </div>
    </main>
</template>
