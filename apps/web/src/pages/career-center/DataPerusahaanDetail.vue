<script setup lang="ts">
/**
 * Career Center "Data Perusahaan" detail (Frontend Vertical Slice v9) —
 * read-only company reference. Reuses the frozen CompanyModerationPresenter
 * detail read model (Career Center is authorised for `internal_note` and the
 * full verification trail). NO review actions — verify / request-revision /
 * reject / suspend / restore live only on the Tinjau Perusahaan page.
 * `mitra_kampus_active` is the derived read-only flag, never a partnership
 * workflow. Document rows are metadata only (no storage reference).
 */
import { Head, Link } from '@inertiajs/vue3'
import AppShell from '@/layouts/AppShell.vue'
import { companyStatusBadgeClass, companyStatusLabel, formatDateTime, statusLabel } from '@/lib/labels'

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
    verified_at: string | null; suspended_at: string | null; submitted_at: string | null
    mitra_kampus_active: boolean
    documents: DocRow[]; members: MemberRow[]; verification_history: ReviewRow[]
}
defineProps<{ company: Company }>()
</script>

<template>
    <Head title="Data Perusahaan" />
    <AppShell persona="career-center" active="data-perusahaan" title="Data Perusahaan">
        <Link href="/data-perusahaan" class="text-sm font-semibold text-[#0061a5] hover:underline">← Kembali ke direktori</Link>

        <div class="mt-3 flex flex-wrap items-center gap-3">
            <h1 class="text-3xl font-bold tracking-tight text-[#002045]">{{ company.name }}</h1>
            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="companyStatusBadgeClass[company.verification_status] ?? 'bg-slate-100 text-slate-700'">
                {{ statusLabel(companyStatusLabel, company.verification_status) }}
            </span>
            <span class="text-xs text-slate-500">
                Kemitraan Kampus: {{ company.mitra_kampus_active ? 'Aktif' : 'Tidak aktif' }} — status kemitraan terpisah dari verifikasi.
            </span>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Profil</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex gap-2"><dt class="w-40 shrink-0 text-slate-500">Email resmi</dt><dd class="text-[#181c1e]">{{ company.official_email ?? '—' }}</dd></div>
                    <div class="flex gap-2"><dt class="w-40 shrink-0 text-slate-500">Telepon</dt><dd class="text-[#181c1e]">{{ company.official_phone ?? '—' }}</dd></div>
                    <div class="flex gap-2"><dt class="w-40 shrink-0 text-slate-500">Website</dt><dd class="text-[#181c1e]">{{ company.website ?? '—' }}</dd></div>
                    <div class="flex gap-2"><dt class="w-40 shrink-0 text-slate-500">Alamat</dt><dd class="text-[#181c1e]">{{ company.address ?? '—' }}</dd></div>
                    <div class="flex gap-2"><dt class="w-40 shrink-0 text-slate-500">Identifikasi legal</dt><dd class="text-[#181c1e]">{{ company.legal_identifier ?? '—' }}</dd></div>
                    <div class="flex gap-2"><dt class="w-40 shrink-0 text-slate-500">Diverifikasi</dt><dd class="text-[#181c1e]">{{ formatDateTime(company.verified_at) }}</dd></div>
                </dl>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Anggota Aktif</h2>
                <ul v-if="company.members.length" class="mt-3 space-y-1 text-sm text-slate-700">
                    <li v-for="m in company.members" :key="m.id">Pengguna #{{ m.user_id }} · {{ m.company_role ?? '—' }}</li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-500">Tidak ada anggota aktif.</p>
                <p class="mt-2 text-xs text-slate-400">Nama dan email anggota tidak ditampilkan sesuai kebijakan privasi.</p>
            </section>
        </div>

        <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Dokumen Legalitas</h2>
            <div v-if="company.documents.length" class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr><th class="py-2 pr-4">Jenis</th><th class="py-2 pr-4">Nomor</th><th class="py-2 pr-4">Terbit</th><th class="py-2 pr-4">Berlaku s.d.</th><th class="py-2">Status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="d in company.documents" :key="d.id">
                            <td class="py-2 pr-4 text-[#181c1e]">{{ d.document_type ?? '—' }}</td>
                            <td class="py-2 pr-4 text-slate-600">{{ d.document_number ?? '—' }}</td>
                            <td class="py-2 pr-4 text-slate-600">{{ d.issued_at ?? '—' }}</td>
                            <td class="py-2 pr-4 text-slate-600">{{ d.expires_at ?? '—' }}</td>
                            <td class="py-2 text-slate-600">{{ d.status ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="mt-3 text-sm text-slate-500">Belum ada dokumen legalitas.</p>
        </section>

        <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Riwayat Verifikasi</h2>
            <ol v-if="company.verification_history.length" class="mt-3 space-y-2 text-sm">
                <li v-for="r in company.verification_history" :key="r.id" class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                    <p class="font-medium text-[#002045]">{{ formatDateTime(r.reviewed_at) }} — {{ r.action }}<span v-if="r.to_status"> → {{ r.to_status }}</span></p>
                    <p v-if="r.reason_category" class="mt-1 text-xs text-slate-500">Kategori: {{ r.reason_category }}</p>
                    <p v-if="r.recruiter_visible_note" class="mt-1 text-slate-700">{{ r.recruiter_visible_note }}</p>
                    <p v-if="r.internal_note" class="mt-1 rounded bg-amber-50 px-2 py-1 text-xs text-amber-800">Catatan internal: {{ r.internal_note }}</p>
                </li>
            </ol>
            <p v-else class="mt-3 text-sm text-slate-500">Belum ada riwayat verifikasi.</p>
        </section>

        <p class="mt-6 text-sm">
            <Link :href="`/verifikasi-perusahaan/${company.id}`" class="font-semibold text-[#0061a5] hover:underline">Buka di Verifikasi Perusahaan untuk mengambil tindakan →</Link>
        </p>
    </AppShell>
</template>
