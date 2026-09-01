<script setup lang="ts">
/**
 * Recruiter "Status Verifikasi" (Recruiter Company & Vacancy Frontend Slice
 * v3). Follows Stitch `recruiter/verifikasi-menunggu`.
 *
 * The recruiter may only submit for verification and resubmit after
 * REVISION_REQUIRED — the same company row, through the frozen
 * `POST /companies/{company}/submit-verification`. Verify / reject / suspend /
 * restore are Career Center authority and appear nowhere here. The revision
 * feedback shows only the recruiter-safe `reason_category` /
 * `recruiter_visible_note`; `internal_note` is never delivered.
 */
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { authRequest, errorText } from '@/lib/auth'
import { companyStatusBadgeClass, companyStatusLabel, formatDateTime, statusLabel } from '@/lib/labels'

interface TrailRow {
    action: string; from_status: string | null; to_status: string | null
    reason_category: string | null; recruiter_visible_note: string | null; reviewed_at: string | null
}

const props = defineProps<{
    company: { id: number; name: string; verification_status: string; verified_at: string | null; suspended_at: string | null } | null
    trail: TrailRow[]
    can_submit: boolean
}>()

const submitting = ref(false)
const message = ref('')
const missing = ref<string[]>([])

const latestRevision = computed(
    () => [...props.trail].reverse().find((r) => r.to_status === 'REVISION_REQUIRED' && r.recruiter_visible_note),
)

const submitLabel = computed(() => (props.company?.verification_status === 'REVISION_REQUIRED' ? 'Kirim Ulang untuk Verifikasi' : 'Ajukan Verifikasi'))

async function submit() {
    if (!props.company) return
    submitting.value = true
    message.value = ''
    missing.value = []
    const { response, payload } = await authRequest(`/companies/${props.company.id}/submit-verification`, {}, 'POST')
    submitting.value = false
    if (!response.ok) {
        message.value = errorText(payload)
        const raw = (payload as { error?: { details?: { missing?: string[] } } }).error?.details?.missing
        if (Array.isArray(raw)) missing.value = raw
        return
    }
    router.reload()
}

const missingLabel: Record<string, string> = {
    organization_type_id: 'Jenis organisasi', industry_id: 'Industri', official_email: 'Email resmi',
    address: 'Alamat lengkap', province_geographic_area_id: 'Provinsi', city_geographic_area_id: 'Kota / kabupaten',
    company_documents: 'Dokumen legalitas',
}
</script>

<template>
    <Head title="Status Verifikasi" />
    <AppShell persona="recruiter" active="status-verifikasi" title="Status Verifikasi">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Status Verifikasi</h1>
        <p class="mt-2 max-w-2xl text-slate-600">Verifikasi perusahaan dilakukan oleh tim Career Center. Anda hanya dapat mengajukan dan mengirim ulang setelah perbaikan diminta.</p>

        <p v-if="message" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-[#93000a]" role="alert">{{ message }}</p>

        <div v-if="!company" class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
            Lengkapi <Link href="/profil-perusahaan" class="font-semibold text-[#0061a5] hover:underline">Profil Perusahaan</Link> terlebih dahulu sebelum mengajukan verifikasi.
        </div>

        <template v-else>
            <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">{{ company.name }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            Status saat ini:
                            <span class="ml-1 rounded-full px-2 py-0.5 font-semibold" :class="companyStatusBadgeClass[company.verification_status] ?? 'bg-slate-100 text-slate-700'">
                                {{ statusLabel(companyStatusLabel, company.verification_status) }}
                            </span>
                        </p>
                        <p v-if="company.verified_at" class="mt-1 text-xs text-slate-500">Terverifikasi pada {{ formatDateTime(company.verified_at) }}</p>
                        <p v-if="company.suspended_at" class="mt-1 text-xs text-slate-500">Ditangguhkan pada {{ formatDateTime(company.suspended_at) }}</p>
                    </div>
                    <button
                        v-if="can_submit"
                        type="button"
                        :disabled="submitting"
                        class="rounded-lg bg-[#0061a5] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#004172] disabled:opacity-60"
                        @click="submit"
                    >
                        {{ submitting ? 'Mengirim…' : submitLabel }}
                    </button>
                </div>

                <p v-if="!can_submit && company.verification_status === 'PENDING_VERIFICATION'" class="mt-4 rounded-lg bg-amber-50 p-3 text-xs text-amber-800">
                    Profil perusahaan sedang ditinjau tim Career Center. Proses ini biasanya memakan waktu 1–2 hari kerja.
                </p>

                <ul v-if="missing.length" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-[#93000a]">
                    <li class="font-semibold">Lengkapi data berikut sebelum mengajukan:</li>
                    <li v-for="m in missing" :key="m" class="ml-4 list-disc">{{ missingLabel[m] ?? m }}</li>
                    <li class="mt-2"><Link href="/profil-perusahaan" class="font-semibold underline">Buka Profil Perusahaan</Link></li>
                </ul>
            </section>

            <section v-if="latestRevision" class="mt-6 rounded-2xl border border-red-200 bg-red-50/60 p-6">
                <h2 class="text-base font-semibold text-[#93000a]">Perbaikan diminta oleh Career Center</h2>
                <p v-if="latestRevision.reason_category" class="mt-2 text-xs font-semibold uppercase tracking-wide text-red-700">Kategori: {{ latestRevision.reason_category }}</p>
                <blockquote class="mt-2 border-l-4 border-red-300 pl-4 text-sm italic text-slate-700">{{ latestRevision.recruiter_visible_note }}</blockquote>
                <p class="mt-2 text-xs text-slate-500">{{ formatDateTime(latestRevision.reviewed_at) }}</p>
            </section>

            <section v-if="trail.length" class="mt-6">
                <h2 class="text-sm font-semibold text-slate-700">Riwayat verifikasi</h2>
                <ol class="mt-3 space-y-2">
                    <li v-for="(row, idx) in [...trail].reverse()" :key="idx" class="rounded-lg border border-slate-200 bg-white p-4 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-medium text-slate-800">{{ row.action }} → {{ statusLabel(companyStatusLabel, row.to_status ?? '') }}</span>
                            <span class="text-xs text-slate-500">{{ formatDateTime(row.reviewed_at) }}</span>
                        </div>
                        <p v-if="row.reason_category" class="mt-1 text-xs text-slate-500">Kategori: {{ row.reason_category }}</p>
                        <p v-if="row.recruiter_visible_note" class="mt-1 text-slate-600">{{ row.recruiter_visible_note }}</p>
                    </li>
                </ol>
            </section>
        </template>
    </AppShell>
</template>
