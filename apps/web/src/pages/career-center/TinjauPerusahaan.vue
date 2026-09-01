<script setup lang="ts">
/**
 * Career Center "Tinjau Perusahaan" review + decision (Frontend Vertical
 * Slice v4). Follows Stitch `career-center/tinjau-perusahaan`.
 *
 * Decisions post to the frozen `POST /companies/{company}/{action}` routes
 * (`CompanyController@review` → `ReviewCompanyVerification`); this page never
 * sets a status itself. Only `eligible_actions` (server-derived from the
 * frozen transition graph) are offered:
 *   PENDING_VERIFICATION → verify · request-revision · reject
 *   VERIFIED             → suspend
 *   SUSPENDED            → restore
 * request-revision / reject / suspend require `reason_category` +
 * `recruiter_visible_note` (INV-029); `internal_note` is optional and stays
 * Career-Center-internal — it must never be shown to the recruiter or public.
 * verify / restore take no reason and are confirmed inline (no browser dialog).
 * Verifying activates no partnership.
 */
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { authRequest, errorText, newIdempotencyKey } from '@/lib/auth'
import { companyStatusBadgeClass, companyStatusLabel, formatDateTime, moderationActionLabel, reviewActionLabel, statusLabel } from '@/lib/labels'

interface ReviewRow {
    id: number; action: string; from_status: string | null; to_status: string | null
    reason_category: string | null; recruiter_visible_note: string | null
    internal_note: string | null; reviewed_at: string | null
}
interface DocRow { id: number; document_type: string | null; document_number: string | null; issued_at: string | null; expires_at: string | null; status: string | null }
interface MemberRow { id: number; user_id: number; company_role: string | null; status: string | null }
interface Company {
    id: number; name: string; slug: string | null; verification_status: string
    website: string | null; official_email: string | null; official_phone: string | null
    address: string | null; legal_identifier: string | null
    organization_type_id: number | null; industry_id: number | null
    province_geographic_area_id: number | null; city_geographic_area_id: number | null
    verified_at: string | null; suspended_at: string | null; submitted_at: string | null
    mitra_kampus_active: boolean
    documents: DocRow[]; members: MemberRow[]; verification_history: ReviewRow[]
}

const props = defineProps<{ company: Company; eligible_actions: string[] }>()

const REASON_ACTIONS = ['request-revision', 'reject', 'suspend']
const active = ref<string | null>(null)
const submitting = ref(false)
const message = ref('')
const form = reactive({ reason_category: '', recruiter_visible_note: '', internal_note: '' })

const needsReason = computed(() => active.value !== null && REASON_ACTIONS.includes(active.value))

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
        message.value = 'Kategori alasan dan catatan untuk perusahaan wajib diisi.'
        return
    }
    submitting.value = true
    message.value = ''
    const body = needsReason.value
        ? { reason_category: form.reason_category, recruiter_visible_note: form.recruiter_visible_note, internal_note: form.internal_note || undefined }
        : {}
    const { response, payload } = await authRequest(
        `/companies/${props.company.id}/${active.value}`, body, 'POST', { 'Idempotency-Key': newIdempotencyKey() },
    )
    submitting.value = false
    if (!response.ok) { message.value = errorText(payload); return }
    active.value = null
    router.reload()
}
</script>

<template>
    <Head :title="`Tinjau — ${company.name}`" />
    <AppShell persona="career-center" active="verifikasi-perusahaan" title="Tinjau Perusahaan">
        <nav class="text-xs text-slate-500"><Link href="/verifikasi-perusahaan" class="hover:underline">Verifikasi Perusahaan</Link> <span class="mx-1">/</span> {{ company.name }}</nav>

        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-[#002045]">{{ company.name }}</h1>
                <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <span class="rounded-full px-2 py-0.5 font-semibold" :class="companyStatusBadgeClass[company.verification_status] ?? 'bg-slate-100 text-slate-700'">
                        {{ statusLabel(companyStatusLabel, company.verification_status) }}
                    </span>
                    <span>Diajukan {{ formatDateTime(company.submitted_at) }}</span>
                    <span>· Kemitraan Kampus: {{ company.mitra_kampus_active ? 'Aktif' : 'Tidak aktif' }} (terpisah dari verifikasi)</span>
                </p>
            </div>
        </div>

        <p v-if="message" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-[#93000a]" role="alert">{{ message }}</p>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <section class="lg:col-span-2 space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-[#002045]">Informasi Perusahaan</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 text-sm">
                        <div><dt class="text-xs uppercase tracking-wide text-slate-400">Nama Resmi</dt><dd class="mt-0.5 text-slate-800">{{ company.name }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-slate-400">Email Resmi</dt><dd class="mt-0.5 text-slate-800">{{ company.official_email ?? '—' }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-slate-400">Telepon</dt><dd class="mt-0.5 text-slate-800">{{ company.official_phone ?? '—' }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-slate-400">Website</dt><dd class="mt-0.5 break-all text-slate-800">{{ company.website ?? '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs uppercase tracking-wide text-slate-400">Alamat</dt><dd class="mt-0.5 text-slate-800">{{ company.address ?? '—' }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-slate-400">Identifikasi Legal</dt><dd class="mt-0.5 text-slate-800">{{ company.legal_identifier ?? '—' }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-slate-400">Jenis Organisasi / Industri</dt><dd class="mt-0.5 text-slate-800">#{{ company.organization_type_id ?? '—' }} / #{{ company.industry_id ?? '—' }}</dd></div>
                    </dl>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-[#002045]">Dokumen Legalitas</h2>
                    <p v-if="!company.documents.length" class="mt-3 text-sm text-slate-500">Tidak ada dokumen aktif.</p>
                    <ul v-else class="mt-3 divide-y divide-slate-100 text-sm">
                        <li v-for="d in company.documents" :key="d.id" class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <span class="font-medium text-slate-800">{{ d.document_type ?? 'Dokumen' }} <span class="text-slate-400">{{ d.document_number ?? '' }}</span></span>
                            <span class="text-xs text-slate-500">Terbit {{ d.issued_at ?? '—' }} · Berakhir {{ d.expires_at ?? '—' }} · {{ d.status ?? '—' }}</span>
                        </li>
                    </ul>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-[#002045]">Riwayat Verifikasi</h2>
                    <p v-if="!company.verification_history.length" class="mt-3 text-sm text-slate-500">Belum ada riwayat.</p>
                    <ol v-else class="mt-3 space-y-3 text-sm">
                        <li v-for="row in [...company.verification_history].reverse()" :key="row.id" class="rounded-lg border border-slate-200 p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-medium text-slate-800">{{ reviewActionLabel[row.action] ?? row.action }} → {{ statusLabel(companyStatusLabel, row.to_status ?? '') }}</span>
                                <span class="text-xs text-slate-500">{{ formatDateTime(row.reviewed_at) }}</span>
                            </div>
                            <p v-if="row.reason_category" class="mt-1 text-xs text-slate-500">Kategori: {{ row.reason_category }}</p>
                            <p v-if="row.recruiter_visible_note" class="mt-1 text-slate-700">Untuk perusahaan: {{ row.recruiter_visible_note }}</p>
                            <p v-if="row.internal_note" class="mt-1 rounded bg-amber-50 px-2 py-1 text-xs text-amber-800">Internal: {{ row.internal_note }}</p>
                        </li>
                    </ol>
                </div>
            </section>

            <aside class="space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-[#002045]">Keputusan</h2>
                    <p v-if="!eligible_actions.length" class="mt-3 text-sm text-slate-500">Tidak ada tindakan verifikasi yang tersedia pada status ini.</p>
                    <div v-else class="mt-3 flex flex-col gap-2">
                        <button
                            v-for="a in eligible_actions"
                            :key="a"
                            type="button"
                            class="rounded-lg px-3 py-2 text-sm font-semibold"
                            :class="a === 'verify' || a === 'restore'
                                ? 'bg-[#0061a5] text-white hover:bg-[#004172]'
                                : (a === 'reject' ? 'border border-red-300 text-red-700 hover:bg-red-50' : 'border border-slate-300 text-slate-700 hover:bg-slate-50')"
                            @click="open(a)"
                        >
                            {{ moderationActionLabel[a] ?? a }}
                        </button>
                    </div>

                    <form v-if="active" class="mt-4 space-y-3 border-t border-slate-200 pt-4" @submit.prevent="submit">
                        <p class="text-sm font-semibold text-slate-800">{{ moderationActionLabel[active] ?? active }}</p>
                        <template v-if="needsReason">
                            <label class="block text-xs font-medium text-slate-700">Kategori Alasan <span class="text-red-500">*</span>
                                <input v-model="form.reason_category" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                            </label>
                            <label class="block text-xs font-medium text-slate-700">Catatan untuk Perusahaan <span class="text-red-500">*</span>
                                <textarea v-model="form.recruiter_visible_note" required rows="3" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                                <span class="mt-0.5 block text-[11px] text-slate-400">Dikirim ke perusahaan.</span>
                            </label>
                            <label class="block text-xs font-medium text-slate-700">Catatan Internal Career Center
                                <textarea v-model="form.internal_note" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                                <span class="mt-0.5 block text-[11px] text-slate-400">Hanya untuk tim internal — tidak pernah dikirim ke perusahaan.</span>
                            </label>
                        </template>
                        <p v-else class="text-xs text-slate-500">Lanjutkan tindakan ini pada {{ company.name }}?</p>
                        <div class="flex gap-3">
                            <button type="submit" :disabled="submitting" class="rounded-lg bg-[#0061a5] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#004172] disabled:opacity-60">
                                {{ submitting ? 'Memproses…' : 'Konfirmasi' }}
                            </button>
                            <button type="button" class="text-xs font-medium text-slate-600 hover:underline" @click="active = null">Batal</button>
                        </div>
                    </form>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm text-sm">
                    <h2 class="text-base font-semibold text-[#002045]">Anggota Aktif</h2>
                    <ul class="mt-3 space-y-1 text-slate-600">
                        <li v-for="m in company.members" :key="m.id">User #{{ m.user_id }} — {{ m.company_role }}</li>
                        <li v-if="!company.members.length" class="text-slate-400">—</li>
                    </ul>
                </div>
            </aside>
        </div>
    </AppShell>
</template>
