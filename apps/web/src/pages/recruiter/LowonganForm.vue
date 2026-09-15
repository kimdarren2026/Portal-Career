<script setup lang="ts">
/**
 * Recruiter "Kelola Lowongan" create / edit form (Recruiter Company & Vacancy
 * Frontend Slice v3). Follows the shared AppShell/card language and Stitch
 * `recruiter/revisi-lowongan` for the revision-feedback banner.
 *
 * - create → `POST /companies/{company}/vacancies`; result is always DRAFT,
 *   never auto-submitted.
 * - edit  → `PATCH /vacancies/{vacancy}` with `If-Match: <current_version>`;
 *   editable only in DRAFT / REVISION_REQUIRED (`editable` prop, server-derived
 *   from `VacancyStatus::isCompanyEditable`). A `STALE_VERSION` (409) shows a
 *   reload prompt.
 * - submit → `POST /vacancies/{vacancy}/submit-review` (owner action, frozen
 *   Idempotency-Key). Submit is an action, never a status.
 * - close  → `POST /vacancies/{vacancy}/close` (owner, PUBLISHED only), with an
 *   inline confirm — no browser dialog.
 *
 * Approve / reject / request-revision / publish / suspend / restore are
 * moderation authority and are absent from this page. Revision feedback shows
 * only the recruiter-safe `reason_category` / `recruiter_visible_note`.
 */
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { authRequest, errorText, newIdempotencyKey } from '@/lib/auth'
import {
    applicationMethodLabel, employmentTypeLabel, employmentTypeOptions, formatDateTime,
    statusLabel, targetAudienceLabel, targetAudienceOptions, vacancyStatusBadgeClass,
    vacancyStatusLabel, vacancyTypeLabel, workplaceModeLabel, workplaceModeOptions,
} from '@/lib/labels'

interface RefRow { id: number; name: string; area_type?: string | null; parent_geographic_area_id?: number | null }
interface VacancyDetail {
    id: number; title: string; description: string | null; responsibilities: string | null
    vacancy_type: string | null; current_status: string | null; target_audience: string | null
    application_method: string | null; employment_type: string | null; workplace_mode: string | null
    openings_count: number | null; location: string | null; minimum_education: string | null
    experience_requirement: string | null; salary_min: number | null; salary_max: number | null
    salary_currency: string | null; external_ats_url: string | null
    province_geographic_area_id: number | null; city_geographic_area_id: number | null
    open_at: string | null; close_at: string | null; current_version: number
}
interface TrailRow {
    action: string; from_status: string | null; to_status: string | null
    reason_category: string | null; recruiter_visible_note: string | null; reviewed_at: string | null
}

const props = defineProps<{
    mode: 'create' | 'edit'
    vacancy: VacancyDetail | null
    company: { id: number; name: string; verification_status: string; can_author_vacancy: boolean } | null
    reference_data: { geographic_areas: RefRow[] }
    authorable_types: string[]
    editable: boolean
    can_submit: boolean
    can_close: boolean
    moderation_trail: TrailRow[]
}>()

const v = props.vacancy
const saving = ref(false)
const acting = ref(false)
const message = ref('')
const stale = ref(false)
const fieldErrors = ref<Record<string, string[]>>({})
const confirmClose = ref(false)

const isCreate = props.mode === 'create'
const status = computed(() => props.vacancy?.current_status ?? 'DRAFT')

const provinces = computed(() => props.reference_data.geographic_areas.filter((a) => a.area_type === 'PROVINCE'))
const cities = computed(() => props.reference_data.geographic_areas.filter(
    (a) => a.area_type === 'CITY' && (!form.province_geographic_area_id || a.parent_geographic_area_id === Number(form.province_geographic_area_id)),
))

const latestRevision = computed(
    () => [...props.moderation_trail].reverse().find((r) => r.to_status === 'REVISION_REQUIRED' && r.recruiter_visible_note),
)

function toDateInput(iso: string | null): string {
    return iso ? iso.slice(0, 10) : ''
}

const form = reactive({
    vacancy_type: v?.vacancy_type ?? props.authorable_types[0] ?? 'COMPANY_EMPLOYMENT',
    title: v?.title ?? '',
    description: v?.description ?? '',
    responsibilities: v?.responsibilities ?? '',
    employment_type: v?.employment_type ?? 'FULL_TIME',
    workplace_mode: v?.workplace_mode ?? '',
    target_audience: v?.target_audience ?? 'PUBLIC',
    application_method: v?.application_method ?? 'IN_PORTAL',
    external_ats_url: v?.external_ats_url ?? '',
    openings_count: v?.openings_count ?? 1,
    location: v?.location ?? '',
    minimum_education: v?.minimum_education ?? '',
    experience_requirement: v?.experience_requirement ?? '',
    salary_min: v?.salary_min ?? null as number | null,
    salary_max: v?.salary_max ?? null as number | null,
    salary_currency: v?.salary_currency ?? '',
    province_geographic_area_id: v?.province_geographic_area_id ?? null as number | null,
    city_geographic_area_id: v?.city_geographic_area_id ?? null as number | null,
    open_at: toDateInput(v?.open_at ?? null),
    close_at: toDateInput(v?.close_at ?? null),
})

function payload() {
    const nn = (x: unknown) => (x === '' || x === null ? null : x)
    const body: Record<string, unknown> = {
        title: form.title,
        description: form.description,
        responsibilities: nn(form.responsibilities),
        employment_type: form.employment_type,
        workplace_mode: nn(form.workplace_mode),
        target_audience: form.target_audience,
        application_method: form.application_method,
        external_ats_url: nn(form.external_ats_url),
        openings_count: Number(form.openings_count),
        location: nn(form.location),
        minimum_education: nn(form.minimum_education),
        experience_requirement: nn(form.experience_requirement),
        salary_min: nn(form.salary_min),
        salary_max: nn(form.salary_max),
        salary_currency: nn(form.salary_currency),
        province_geographic_area_id: nn(form.province_geographic_area_id),
        city_geographic_area_id: nn(form.city_geographic_area_id),
        open_at: form.open_at ? `${form.open_at}T00:00:00+07:00` : null,
        close_at: form.close_at ? `${form.close_at}T23:59:00+07:00` : null,
    }
    if (isCreate) body.vacancy_type = form.vacancy_type
    return body
}

async function save() {
    saving.value = true
    message.value = ''
    stale.value = false
    fieldErrors.value = {}
    const { response, payload: resBody } = isCreate
        ? await authRequest(`/companies/${props.company!.id}/vacancies`, payload(), 'POST')
        : await authRequest(`/vacancies/${props.vacancy!.id}`, payload(), 'PATCH', { 'If-Match': String(props.vacancy!.current_version) })
    saving.value = false
    if (!response.ok) {
        const code = (resBody as { error?: { code?: string } }).error?.code
        if (code === 'STALE_VERSION') { stale.value = true; return }
        message.value = errorText(resBody)
        fieldErrors.value = (resBody as { error?: { details?: { fields?: Record<string, string[]> } } }).error?.details?.fields ?? {}
        return
    }
    if (isCreate) {
        const newId = (resBody as { data?: { id?: number } }).data?.id
        router.visit(newId ? `/kelola-lowongan/${newId}` : '/kelola-lowongan')
    } else {
        router.reload()
    }
}

async function submitForReview() {
    if (!props.vacancy) return
    acting.value = true
    message.value = ''
    const { response, payload } = await authRequest(
        `/vacancies/${props.vacancy.id}/submit-review`, {}, 'POST', { 'Idempotency-Key': newIdempotencyKey() },
    )
    acting.value = false
    if (!response.ok) { message.value = errorText(payload); return }
    router.reload()
}

async function closeVacancy() {
    if (!props.vacancy) return
    acting.value = true
    message.value = ''
    const { response, payload } = await authRequest(
        `/vacancies/${props.vacancy.id}/close`, {}, 'POST', { 'Idempotency-Key': newIdempotencyKey() },
    )
    acting.value = false
    confirmClose.value = false
    if (!response.ok) { message.value = errorText(payload); return }
    router.reload()
}
</script>

<template>
    <Head :title="isCreate ? 'Buat Lowongan' : (vacancy?.title ?? 'Lowongan')" />
    <AppShell persona="recruiter" active="kelola-lowongan" :title="isCreate ? 'Buat Lowongan' : 'Detail Lowongan'">
        <nav class="text-xs text-slate-500"><Link href="/kelola-lowongan" class="font-medium text-[#1671bc] hover:underline">Daftar Lowongan</Link> <span class="mx-1">/</span> {{ isCreate ? 'Baru' : (vacancy?.title ?? '') }}</nav>

        <section class="mt-4 overflow-hidden rounded-2xl bg-gradient-to-br from-[#002b5b] via-[#064d87] to-[#1671bc] px-6 py-7 text-white shadow-lg shadow-blue-950/10 sm:px-8">
            <div class="flex flex-wrap items-start justify-between gap-5">
                <div class="max-w-2xl">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-100">Ruang kerja recruiter</p>
                    <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">{{ isCreate ? 'Buat lowongan baru' : vacancy?.title }}</h1>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-blue-50/90">
                        {{ isCreate ? 'Lengkapi informasi berikut agar kandidat memahami peran dan dapat melamar dengan tepat.' : 'Perbarui informasi lowongan Anda, lalu ajukan kembali saat sudah siap.' }}
                    </p>
                </div>
                <span v-if="!isCreate" class="rounded-full border border-white/20 bg-white/15 px-3 py-1.5 text-xs font-bold backdrop-blur" :class="vacancyStatusBadgeClass[status] ?? 'text-white'">
                    {{ statusLabel(vacancyStatusLabel, status) }}
                </span>
                <span v-else class="rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-semibold text-blue-50">Disimpan sebagai draf</span>
            </div>
            <div class="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-xs text-blue-100">
                <span><span class="mr-1 text-white">•</span> Kolom bertanda <b class="text-white">*</b> wajib diisi</span>
                <span><span class="mr-1 text-white">•</span> Draf dapat diperbarui sebelum diajukan</span>
            </div>
        </section>

        <p v-if="message" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-[#93000a]" role="alert">{{ message }}</p>
        <p v-if="stale" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800" role="alert">
            Versi lowongan sudah berubah. <button type="button" class="font-semibold underline" @click="router.reload()">Muat ulang</button> sebelum menyimpan.
        </p>

        <p v-if="isCreate && company && !company.can_author_vacancy" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Perusahaan harus "Terverifikasi" sebelum lowongan dapat dibuat. Anda tetap dapat mengisi formulir, namun pengiriman akan ditolak server hingga verifikasi selesai.
        </p>

        <!-- Revision feedback (recruiter-safe only) -->
        <section v-if="latestRevision" class="mt-6 rounded-2xl border border-red-200 bg-red-50/60 p-6">
            <h2 class="text-base font-semibold text-[#93000a]">Lowongan ini memerlukan revisi</h2>
            <p v-if="latestRevision.reason_category" class="mt-2 text-xs font-semibold uppercase tracking-wide text-red-700">Kategori: {{ latestRevision.reason_category }}</p>
            <blockquote class="mt-2 border-l-4 border-red-300 pl-4 text-sm italic text-slate-700">{{ latestRevision.recruiter_visible_note }}</blockquote>
            <p class="mt-2 text-xs text-slate-500">{{ formatDateTime(latestRevision.reviewed_at) }}</p>
        </section>

        <p v-if="!isCreate && !editable" class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
            Lowongan hanya dapat diubah saat berstatus "Draf" atau "Perlu Perbaikan". Rincian di bawah bersifat baca-saja.
        </p>

        <form class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" @submit.prevent="save">
            <fieldset :disabled="(!isCreate && !editable) || saving" class="space-y-6 p-4 sm:p-6">
                <section class="form-section">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h2 class="text-base font-bold text-[#002b5b]">Informasi utama</h2>
                            <p class="mt-1 text-sm text-slate-600">Buat judul yang spesifik dan jelaskan peran ini secara ringkas.</p>
                        </div>
                        <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-[#075ea6]">Langkah 1</span>
                    </div>
                    <div class="mt-5 space-y-5">
                        <label v-if="isCreate" class="form-label">Jenis lowongan <span class="text-red-500">*</span>
                            <select v-model="form.vacancy_type" class="form-control mt-1.5">
                                <option v-for="t in authorable_types" :key="t" :value="t">{{ vacancyTypeLabel[t] ?? t }}</option>
                            </select>
                        </label>
                        <div v-else class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">Jenis lowongan: <span class="font-semibold text-slate-800">{{ statusLabel(vacancyTypeLabel, vacancy?.vacancy_type) }}</span></div>

                        <label class="form-label">Judul lowongan <span class="text-red-500">*</span>
                            <input v-model="form.title" required minlength="3" placeholder="Contoh: Frontend Engineer" class="form-control mt-1.5" :aria-invalid="Boolean(fieldErrors.title)" />
                            <span class="form-hint">Gunakan nama peran yang mudah dipahami kandidat.</span>
                            <span v-if="fieldErrors.title" class="mt-1 block text-xs font-medium text-red-600">{{ fieldErrors.title[0] }}</span>
                        </label>

                        <label class="form-label">Deskripsi <span class="text-red-500">*</span>
                            <textarea v-model="form.description" required rows="5" placeholder="Jelaskan tujuan peran, tim, dan dampak pekerjaan ini." class="form-control mt-1.5" :aria-invalid="Boolean(fieldErrors.description)" />
                            <span class="form-hint">Kandidat akan melihat informasi ini sebelum melamar.</span>
                            <span v-if="fieldErrors.description" class="mt-1 block text-xs font-medium text-red-600">{{ fieldErrors.description[0] }}</span>
                        </label>

                        <label class="form-label">Tanggung jawab dan kualifikasi
                            <textarea v-model="form.responsibilities" rows="4" placeholder="Contoh: Mengembangkan fitur web, berkolaborasi dengan tim produk, dan menguasai Vue atau React." class="form-control mt-1.5" />
                        </label>
                    </div>
                </section>

                <section class="form-section">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h2 class="text-base font-bold text-[#002b5b]">Pengaturan pekerjaan dan lamaran</h2>
                            <p class="mt-1 text-sm text-slate-600">Tentukan bentuk kerja serta cara kandidat mengirim lamaran.</p>
                        </div>
                        <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-[#075ea6]">Langkah 2</span>
                    </div>
                    <div class="mt-5 grid gap-5 md:grid-cols-3">
                        <label class="form-label">Jenis kerja <span class="text-red-500">*</span>
                            <select v-model="form.employment_type" class="form-control mt-1.5">
                                <option v-for="e in employmentTypeOptions" :key="e" :value="e">{{ employmentTypeLabel[e] }}</option>
                            </select>
                        </label>
                        <label class="form-label">Cara kerja
                            <select v-model="form.workplace_mode" class="form-control mt-1.5">
                                <option value="">Tidak ditentukan</option>
                                <option v-for="w in workplaceModeOptions" :key="w" :value="w">{{ workplaceModeLabel[w] }}</option>
                            </select>
                        </label>
                        <label class="form-label">Jumlah posisi <span class="text-red-500">*</span>
                            <input v-model.number="form.openings_count" type="number" min="1" required class="form-control mt-1.5" />
                        </label>
                    </div>
                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        <label class="form-label">Target kandidat <span class="text-red-500">*</span>
                            <select v-model="form.target_audience" class="form-control mt-1.5">
                                <option v-for="a in targetAudienceOptions" :key="a" :value="a">{{ targetAudienceLabel[a] }}</option>
                            </select>
                        </label>
                        <label class="form-label">Metode lamaran <span class="text-red-500">*</span>
                            <select v-model="form.application_method" class="form-control mt-1.5">
                                <option value="IN_PORTAL">{{ applicationMethodLabel.IN_PORTAL }}</option>
                                <option value="EXTERNAL_ATS">{{ applicationMethodLabel.EXTERNAL_ATS }}</option>
                            </select>
                        </label>
                    </div>
                    <label v-if="form.application_method === 'EXTERNAL_ATS'" class="form-label mt-5">URL ATS eksternal (HTTPS) <span class="text-red-500">*</span>
                        <input v-model="form.external_ats_url" type="url" placeholder="https://perusahaan.example/karier" class="form-control mt-1.5" :aria-invalid="Boolean(fieldErrors.external_ats_url)" />
                        <span v-if="fieldErrors.external_ats_url" class="mt-1 block text-xs font-medium text-red-600">{{ fieldErrors.external_ats_url[0] }}</span>
                    </label>
                </section>

                <section class="form-section">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h2 class="text-base font-bold text-[#002b5b]">Kualifikasi dan lokasi</h2>
                            <p class="mt-1 text-sm text-slate-600">Informasi tambahan ini membantu kandidat menilai kecocokan mereka.</p>
                        </div>
                        <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-[#075ea6]">Langkah 3</span>
                    </div>
                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        <label class="form-label">Pendidikan minimum
                            <input v-model="form.minimum_education" placeholder="Contoh: D3/S1 Teknik Informatika" class="form-control mt-1.5" />
                        </label>
                        <label class="form-label">Pengalaman
                            <input v-model="form.experience_requirement" placeholder="Contoh: Minimal 2 tahun" class="form-control mt-1.5" />
                        </label>
                    </div>
                    <div class="mt-5 grid gap-5 md:grid-cols-3">
                        <label class="form-label">Provinsi
                            <select v-model="form.province_geographic_area_id" class="form-control mt-1.5">
                                <option :value="null">Pilih provinsi</option>
                                <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </label>
                        <label class="form-label">Kota / kabupaten
                            <select v-model="form.city_geographic_area_id" class="form-control mt-1.5">
                                <option :value="null">Pilih kota / kabupaten</option>
                                <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </label>
                        <label class="form-label">Catatan lokasi
                            <input v-model="form.location" placeholder="Contoh: Jakarta Selatan" class="form-control mt-1.5" />
                        </label>
                    </div>
                </section>

                <section class="form-section">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h2 class="text-base font-bold text-[#002b5b]">Kompensasi dan periode</h2>
                            <p class="mt-1 text-sm text-slate-600">Opsional, namun menambah kejelasan bagi kandidat.</p>
                        </div>
                        <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-[#075ea6]">Langkah 4</span>
                    </div>
                    <div class="mt-5 grid gap-5 md:grid-cols-3">
                        <label class="form-label">Gaji minimum
                            <input v-model.number="form.salary_min" type="number" min="0" placeholder="Contoh: 7000000" class="form-control mt-1.5" />
                        </label>
                        <label class="form-label">Gaji maksimum
                            <input v-model.number="form.salary_max" type="number" min="0" placeholder="Contoh: 10000000" class="form-control mt-1.5" />
                        </label>
                        <label class="form-label">Mata uang (3 huruf)
                            <input v-model="form.salary_currency" maxlength="3" placeholder="IDR" class="form-control mt-1.5 uppercase" />
                        </label>
                    </div>
                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        <label class="form-label">Tanggal buka
                            <input v-model="form.open_at" type="date" class="form-control mt-1.5" />
                        </label>
                        <label class="form-label">Tanggal tutup
                            <input v-model="form.close_at" type="date" class="form-control mt-1.5" />
                        </label>
                    </div>
                </section>
            </fieldset>

            <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 bg-slate-50/80 px-4 py-4 sm:px-6">
                <p v-if="isCreate" class="mr-auto text-xs leading-5 text-slate-500">Lowongan baru akan disimpan sebagai draf dan belum terlihat oleh kandidat.</p>
                <button
                    v-if="isCreate || editable"
                    type="submit"
                    :disabled="saving"
                    class="rounded-xl bg-[#0061a5] px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#004172] focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ saving ? 'Menyimpan…' : (isCreate ? 'Simpan sebagai Draf' : 'Simpan Perubahan') }}
                </button>
                <Link href="/kelola-lowongan" class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Kembali</Link>
            </div>
        </form>

        <!-- Owner lifecycle actions (never moderation) -->
        <div v-if="!isCreate" class="mt-6 flex flex-wrap items-center gap-3">
            <button
                v-if="can_submit"
                type="button"
                :disabled="acting"
                class="rounded-lg bg-[#0061a5] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#004172] disabled:opacity-60"
                @click="submitForReview"
            >
                {{ acting ? 'Memproses…' : 'Ajukan untuk Ditinjau' }}
            </button>

            <template v-if="can_close">
                <button
                    v-if="!confirmClose"
                    type="button"
                    class="rounded-lg border border-red-300 px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50"
                    @click="confirmClose = true"
                >
                    Tutup Lowongan
                </button>
                <span v-else class="flex items-center gap-2 text-sm">
                    <span class="text-slate-600">Tutup lowongan ini secara permanen?</span>
                    <button type="button" :disabled="acting" class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-60" @click="closeVacancy">Ya, Tutup</button>
                    <button type="button" class="text-xs font-medium text-slate-600 hover:underline" @click="confirmClose = false">Batal</button>
                </span>
            </template>
        </div>

        <p v-if="!isCreate" class="mt-4 text-xs text-slate-400">
            Persetujuan, penolakan, penayangan, penangguhan dan pemulihan lowongan adalah wewenang Career Center dan tidak tersedia di sini.
        </p>
    </AppShell>
</template>
