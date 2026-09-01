<script setup lang="ts">
/**
 * Career Center "Tinjau Lowongan" review + moderation decision (Frontend
 * Vertical Slice v4). Follows Stitch `career-center/tinjau-lowongan`.
 *
 * Decisions post to the frozen `POST /vacancies/{vacancy}/{action}` routes
 * (`VacancyLifecycleController` → `ModerateVacancy`); this page never edits
 * recruiter-authored vacancy content and never sets a status itself. Only
 * `eligible_actions` (server-derived from the frozen B-1/B-2/B-3 graph) are
 * offered:
 *   PENDING_REVIEW → approve · request-revision · reject
 *   PUBLISHED      → suspend · close
 *   SUSPENDED      → restore
 * `approve` resolves deterministically to PUBLISHED (open_at ≤ now < close_at)
 * or SCHEDULED (now < open_at); there is no separate publish action (B-4).
 * request-revision / reject / suspend require `reason_category` +
 * `recruiter_visible_note` (INV-029); `internal_note` stays internal.
 * Every action carries an `Idempotency-Key` (frozen: Idempotency REQUIRED).
 */
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { authRequest, errorText, newIdempotencyKey } from '@/lib/auth'
import {
    applicationMethodLabel, companyStatusLabel, employmentTypeLabel, formatDateTime,
    moderationActionLabel, reviewActionLabel, statusLabel, targetAudienceLabel,
    vacancyStatusBadgeClass, vacancyStatusLabel, vacancyTypeLabel, workplaceModeLabel,
} from '@/lib/labels'

interface ReviewRow {
    action: string; from_status: string | null; to_status: string | null
    reason_category: string | null; recruiter_visible_note: string | null
    internal_note: string | null; reviewed_at: string | null
}
interface Vacancy {
    id: number; title: string; description: string | null; responsibilities: string | null
    vacancy_type: string | null; current_status: string | null; target_audience: string | null
    application_method: string | null; external_ats_url: string | null
    employment_type: string | null; workplace_mode: string | null; openings_count: number | null
    location: string | null; minimum_education: string | null; experience_requirement: string | null
    salary_min: number | null; salary_max: number | null; salary_currency: string | null
    open_at: string | null; close_at: string | null; published_at: string | null
}

const props = defineProps<{
    vacancy: Vacancy
    company: { id: number; name: string; verification_status: string } | null
    eligible_actions: string[]
    approve_effect: 'scheduled' | 'published' | 'blocked_window' | 'blocked_dates' | null
    moderation_trail: ReviewRow[]
}>()

const REASON_ACTIONS = ['request-revision', 'reject', 'suspend']
const active = ref<string | null>(null)
const submitting = ref(false)
const message = ref('')
const form = reactive({ reason_category: '', recruiter_visible_note: '', internal_note: '' })

const needsReason = computed(() => active.value !== null && REASON_ACTIONS.includes(active.value))

const approveHint = computed(() => {
    switch (props.approve_effect) {
        case 'published': return 'Menyetujui sekarang akan langsung mempublikasikan lowongan (dalam periode tayang).'
        case 'scheduled': return 'Menyetujui sekarang akan menjadwalkan lowongan; sistem mempublikasikan saat tanggal buka tercapai.'
        case 'blocked_window': return 'Periode tayang telah berakhir — persetujuan akan ditolak server.'
        case 'blocked_dates': return 'Tanggal buka/tutup belum lengkap — persetujuan akan ditolak server.'
        default: return ''
    }
})

function open(action: string) {
    active.value = action
    message.value = ''
    form.reason_category = ''
    form.recruiter_visible_note = ''
    form.internal_note = ''
}

async function submit() {
    if (!active.value) return
    if (needsReason.value && (!form.reason_category.trim() || !form.recruiter_visible_note.trim())) {
        message.value = 'Kategori alasan dan catatan untuk recruiter wajib diisi.'
        return
    }
    submitting.value = true
    message.value = ''
    const body = needsReason.value
        ? { reason_category: form.reason_category, recruiter_visible_note: form.recruiter_visible_note, internal_note: form.internal_note || undefined }
        : {}
    const { response, payload } = await authRequest(
        `/vacancies/${props.vacancy.id}/${active.value}`, body, 'POST', { 'Idempotency-Key': newIdempotencyKey() },
    )
    submitting.value = false
    if (!response.ok) { message.value = errorText(payload); return }
    active.value = null
    router.reload()
}

function money(v: number | null): string {
    return v === null ? '—' : new Intl.NumberFormat('id-ID').format(v)
}
</script>

<template>
    <Head :title="`Tinjau — ${vacancy.title}`" />
    <AppShell persona="career-center" active="moderasi-lowongan" title="Tinjau Lowongan">
        <nav class="text-xs text-slate-500"><Link href="/moderasi-lowongan" class="hover:underline">Moderasi Lowongan</Link> <span class="mx-1">/</span> {{ vacancy.title }}</nav>

        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-[#002045]">{{ vacancy.title }}</h1>
                <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <span class="rounded-full px-2 py-0.5 font-semibold" :class="vacancyStatusBadgeClass[vacancy.current_status ?? ''] ?? 'bg-slate-100 text-slate-700'">
                        {{ statusLabel(vacancyStatusLabel, vacancy.current_status) }}
                    </span>
                    <span v-if="company">{{ company.name }} · {{ statusLabel(companyStatusLabel, company.verification_status) }}</span>
                    <span>· {{ statusLabel(vacancyTypeLabel, vacancy.vacancy_type) }}</span>
                </p>
            </div>
        </div>

        <p v-if="message" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-[#93000a]" role="alert">{{ message }}</p>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <section class="lg:col-span-2 space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-[#002045]">Informasi Posisi</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-4 text-sm">
                        <div><dt class="text-xs uppercase tracking-wide text-slate-400">Jenis Kerja</dt><dd class="mt-0.5 text-slate-800">{{ statusLabel(employmentTypeLabel, vacancy.employment_type) }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-slate-400">Cara Kerja</dt><dd class="mt-0.5 text-slate-800">{{ statusLabel(workplaceModeLabel, vacancy.workplace_mode) }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-slate-400">Posisi</dt><dd class="mt-0.5 text-slate-800">{{ vacancy.openings_count ?? '—' }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-slate-400">Lokasi</dt><dd class="mt-0.5 text-slate-800">{{ vacancy.location ?? '—' }}</dd></div>
                    </dl>
                    <div class="mt-4 whitespace-pre-wrap text-sm text-slate-700">{{ vacancy.description }}</div>
                    <div v-if="vacancy.responsibilities" class="mt-3 whitespace-pre-wrap text-sm text-slate-700">
                        <p class="font-semibold text-slate-800">Tanggung Jawab / Kualifikasi</p>
                        {{ vacancy.responsibilities }}
                    </div>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-3 text-sm">
                        <div><dt class="text-xs uppercase tracking-wide text-slate-400">Pendidikan Minimum</dt><dd class="mt-0.5 text-slate-800">{{ vacancy.minimum_education ?? '—' }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-slate-400">Pengalaman</dt><dd class="mt-0.5 text-slate-800">{{ vacancy.experience_requirement ?? '—' }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-slate-400">Gaji</dt><dd class="mt-0.5 text-slate-800">{{ money(vacancy.salary_min) }} – {{ money(vacancy.salary_max) }} {{ vacancy.salary_currency ?? '' }}</dd></div>
                    </dl>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-[#002045]">Riwayat Moderasi</h2>
                    <p v-if="!moderation_trail.length" class="mt-3 text-sm text-slate-500">Belum ada riwayat.</p>
                    <ol v-else class="mt-3 space-y-3 text-sm">
                        <li v-for="(row, idx) in [...moderation_trail].reverse()" :key="idx" class="rounded-lg border border-slate-200 p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-medium text-slate-800">{{ reviewActionLabel[row.action] ?? row.action }} → {{ statusLabel(vacancyStatusLabel, row.to_status ?? '') }}</span>
                                <span class="text-xs text-slate-500">{{ formatDateTime(row.reviewed_at) }}</span>
                            </div>
                            <p v-if="row.reason_category" class="mt-1 text-xs text-slate-500">Kategori: {{ row.reason_category }}</p>
                            <p v-if="row.recruiter_visible_note" class="mt-1 text-slate-700">Untuk recruiter: {{ row.recruiter_visible_note }}</p>
                            <p v-if="row.internal_note" class="mt-1 rounded bg-amber-50 px-2 py-1 text-xs text-amber-800">Internal: {{ row.internal_note }}</p>
                        </li>
                    </ol>
                </div>
            </section>

            <aside class="space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm text-sm">
                    <h2 class="text-base font-semibold text-[#002045]">Target &amp; Publikasi</h2>
                    <dl class="mt-3 space-y-3">
                        <div><dt class="text-xs uppercase tracking-wide text-slate-400">Target Kandidat</dt><dd class="mt-0.5 text-slate-800">{{ statusLabel(targetAudienceLabel, vacancy.target_audience) }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-slate-400">Metode Lamaran</dt><dd class="mt-0.5 text-slate-800">{{ statusLabel(applicationMethodLabel, vacancy.application_method) }}</dd></div>
                        <div v-if="vacancy.external_ats_url"><dt class="text-xs uppercase tracking-wide text-slate-400">URL ATS</dt><dd class="mt-0.5 break-all text-slate-800">{{ vacancy.external_ats_url }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-slate-400">Periode Tayang</dt><dd class="mt-0.5 text-slate-800">{{ formatDateTime(vacancy.open_at) }} – {{ formatDateTime(vacancy.close_at) }}</dd></div>
                        <div v-if="vacancy.published_at"><dt class="text-xs uppercase tracking-wide text-slate-400">Dipublikasikan</dt><dd class="mt-0.5 text-slate-800">{{ formatDateTime(vacancy.published_at) }}</dd></div>
                    </dl>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-[#002045]">Keputusan Moderasi</h2>
                    <p v-if="!eligible_actions.length" class="mt-3 text-sm text-slate-500">Tidak ada tindakan moderasi yang tersedia pada status ini.</p>
                    <div v-else class="mt-3 flex flex-col gap-2">
                        <button
                            v-for="a in eligible_actions"
                            :key="a"
                            type="button"
                            class="rounded-lg px-3 py-2 text-sm font-semibold"
                            :class="a === 'approve' || a === 'restore'
                                ? 'bg-[#0061a5] text-white hover:bg-[#004172]'
                                : (a === 'reject' ? 'border border-red-300 text-red-700 hover:bg-red-50' : 'border border-slate-300 text-slate-700 hover:bg-slate-50')"
                            @click="open(a)"
                        >
                            {{ moderationActionLabel[a] ?? a }}
                        </button>
                    </div>

                    <form v-if="active" class="mt-4 space-y-3 border-t border-slate-200 pt-4" @submit.prevent="submit">
                        <p class="text-sm font-semibold text-slate-800">{{ moderationActionLabel[active] ?? active }}</p>
                        <p v-if="active === 'approve' && approveHint" class="rounded-lg bg-slate-50 p-2 text-xs text-slate-600">{{ approveHint }}</p>
                        <template v-if="needsReason">
                            <label class="block text-xs font-medium text-slate-700">Kategori Alasan <span class="text-red-500">*</span>
                                <input v-model="form.reason_category" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                            </label>
                            <label class="block text-xs font-medium text-slate-700">Catatan untuk Recruiter <span class="text-red-500">*</span>
                                <textarea v-model="form.recruiter_visible_note" required rows="3" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                                <span class="mt-0.5 block text-[11px] text-slate-400">Dikirim ke recruiter.</span>
                            </label>
                            <label class="block text-xs font-medium text-slate-700">Catatan Internal (Hanya Admin)
                                <textarea v-model="form.internal_note" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                                <span class="mt-0.5 block text-[11px] text-slate-400">Tidak pernah terlihat oleh recruiter atau publik.</span>
                            </label>
                        </template>
                        <p v-else-if="active !== 'approve'" class="text-xs text-slate-500">Lanjutkan tindakan ini?</p>
                        <div class="flex gap-3">
                            <button type="submit" :disabled="submitting" class="rounded-lg bg-[#0061a5] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#004172] disabled:opacity-60">
                                {{ submitting ? 'Memproses…' : 'Konfirmasi' }}
                            </button>
                            <button type="button" class="text-xs font-medium text-slate-600 hover:underline" @click="active = null">Batal</button>
                        </div>
                    </form>

                    <p class="mt-4 text-[11px] text-slate-400">Publikasi manual tidak tersedia — lowongan tayang melalui persetujuan dalam periode atau penjadwal sistem.</p>
                </div>
            </aside>
        </div>
    </AppShell>
</template>
