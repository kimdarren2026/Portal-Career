<script setup lang="ts">
/**
 * Recruiter "Kelola Lowongan" list (Recruiter Company & Vacancy Frontend Slice
 * v3). Company-scoped server-side read (`ListVacancies` / `VacancyScope`) with
 * pagination — never a global fetch filtered in the browser. Authoring is
 * offered only when the owning company is VERIFIED and the actor is an active
 * member (`company.can_author_vacancy`, server-derived); the backend
 * `VACANCY_COMPANY_NOT_VERIFIED` gate remains the authority.
 */
import { Head, Link, router } from '@inertiajs/vue3'
import { reactive } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import {
    companyStatusLabel, formatDateTime, statusLabel, targetAudienceLabel,
    vacancyStatusBadgeClass, vacancyStatusLabel, vacancyTypeLabel,
} from '@/lib/labels'

interface VacancyRow {
    id: number; title: string; vacancy_type: string | null; current_status: string | null
    target_audience: string | null; created_at: string | null
}
interface Pagination { page: number; per_page: number; total: number; last_page: number }

const props = defineProps<{
    items: VacancyRow[]
    pagination: Pagination
    filters: Record<string, string>
    company: { id: number; name: string; verification_status: string; can_author_vacancy: boolean } | null
}>()

const statusOptions = Object.keys(vacancyStatusLabel)
const filterForm = reactive({ status: props.filters.status ?? '', q: props.filters.q ?? '' })

function applyFilters() {
    const query: Record<string, string> = {}
    if (filterForm.status) query.status = filterForm.status
    if (filterForm.q) query.q = filterForm.q
    router.get('/kelola-lowongan', query, { preserveState: true, replace: true })
}

function goToPage(page: number) {
    router.get('/kelola-lowongan', { ...props.filters, page }, { preserveState: true, replace: true })
}
</script>

<template>
    <Head title="Lowongan" />
    <AppShell persona="recruiter" active="kelola-lowongan" title="Kelola Lowongan">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Kelola Lowongan</h1>
                <p class="mt-2 max-w-2xl text-slate-600">Lowongan milik {{ company?.name ?? 'perusahaan Anda' }}. Lowongan baru dibuat sebagai "Draf" lalu diajukan untuk ditinjau.</p>
            </div>
            <Link
                v-if="company?.can_author_vacancy"
                href="/kelola-lowongan/baru"
                class="rounded-lg bg-[#0061a5] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#004172]"
            >
                Buat Lowongan
            </Link>
        </div>

        <p
            v-if="company && !company.can_author_vacancy"
            class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"
        >
            Pembuatan lowongan tersedia setelah perusahaan berstatus "Terverifikasi". Status perusahaan saat ini:
            <span class="font-semibold">{{ statusLabel(companyStatusLabel, company.verification_status) }}</span>.
            <Link href="/status-verifikasi" class="font-semibold underline">Lihat Status Verifikasi</Link>
        </p>
        <p v-else-if="!company" class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
            <Link href="/profil-perusahaan" class="font-semibold text-[#0061a5] underline">Lengkapi profil perusahaan</Link> terlebih dahulu.
        </p>

        <form class="mt-6 flex flex-wrap items-end gap-3" @submit.prevent="applyFilters">
            <label class="text-xs font-medium text-slate-600">Status
                <select v-model="filterForm.status" class="mt-1 block rounded-lg border-slate-300 text-sm">
                    <option value="">Semua status</option>
                    <option v-for="s in statusOptions" :key="s" :value="s">{{ vacancyStatusLabel[s] }}</option>
                </select>
            </label>
            <label class="text-xs font-medium text-slate-600">Cari judul
                <input v-model="filterForm.q" class="mt-1 block rounded-lg border-slate-300 text-sm" placeholder="Judul lowongan" />
            </label>
            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50">Terapkan</button>
        </form>

        <p v-if="!items.length" class="mt-6 rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
            Belum ada lowongan.
        </p>
        <div v-else class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">Judul</th><th class="px-4 py-3">Jenis</th><th class="px-4 py-3">Target</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Dibuat</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="row in items" :key="row.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <Link :href="`/kelola-lowongan/${row.id}`" class="font-medium text-[#0061a5] hover:underline">{{ row.title }}</Link>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ statusLabel(vacancyTypeLabel, row.vacancy_type) }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ statusLabel(targetAudienceLabel, row.target_audience) }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="vacancyStatusBadgeClass[row.current_status ?? ''] ?? 'bg-slate-100 text-slate-700'">
                                {{ statusLabel(vacancyStatusLabel, row.current_status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ formatDateTime(row.created_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="pagination.last_page > 1" class="mt-4 flex items-center gap-2">
            <button type="button" :disabled="pagination.page <= 1" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm disabled:opacity-40" @click="goToPage(pagination.page - 1)">Sebelumnya</button>
            <span class="text-xs text-slate-500">Halaman {{ pagination.page }} / {{ pagination.last_page }}</span>
            <button type="button" :disabled="pagination.page >= pagination.last_page" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm disabled:opacity-40" @click="goToPage(pagination.page + 1)">Berikutnya</button>
        </div>
    </AppShell>
</template>
