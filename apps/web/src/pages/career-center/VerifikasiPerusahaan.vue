<script setup lang="ts">
/**
 * Career Center "Verifikasi Perusahaan" queue (Frontend Vertical Slice v4).
 * Follows Stitch `career-center/verifikasi-perusahaan` and the shared
 * AppShell/table language. Server-scoped read (`CompanyScope`, global reader),
 * paginated — never a global fetch filtered in the browser. Review + decision
 * happen on the detail page; every decision posts to the frozen
 * `POST /companies/{company}/{action}` routes.
 */
import { Head, Link, router } from '@inertiajs/vue3'
import { reactive } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { companyStatusBadgeClass, companyStatusLabel, formatDateTime, statusLabel } from '@/lib/labels'

interface CompanyRow {
    id: number; name: string; verification_status: string
    official_email: string | null; submitted_at: string | null; updated_at: string | null
}
interface Pagination { page: number; per_page: number; total: number; last_page: number }

const props = defineProps<{
    items: CompanyRow[]
    pagination: Pagination
    filters: Record<string, string>
}>()

const statusOptions = Object.keys(companyStatusLabel)
const filterForm = reactive({ status: props.filters.status ?? '', q: props.filters.q ?? '' })

function applyFilters() {
    const query: Record<string, string> = {}
    if (filterForm.status) query.status = filterForm.status
    if (filterForm.q) query.q = filterForm.q
    router.get('/verifikasi-perusahaan', query, { preserveState: true, replace: true })
}

function goToPage(page: number) {
    router.get('/verifikasi-perusahaan', { ...props.filters, page }, { preserveState: true, replace: true })
}
</script>

<template>
    <Head title="Verifikasi Perusahaan" />
    <AppShell persona="career-center" active="verifikasi-perusahaan" title="Verifikasi Perusahaan">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Verifikasi Perusahaan</h1>
        <p class="mt-2 max-w-2xl text-slate-600">Tinjau profil perusahaan sebelum perusahaan dapat mempublikasikan lowongan ke portal karir mahasiswa. Verifikasi tidak mengaktifkan kemitraan.</p>

        <form class="mt-6 flex flex-wrap items-end gap-3" @submit.prevent="applyFilters">
            <label class="text-xs font-medium text-slate-600">Status
                <select v-model="filterForm.status" class="mt-1 block rounded-lg border-slate-300 text-sm">
                    <option value="">Semua status</option>
                    <option v-for="s in statusOptions" :key="s" :value="s">{{ companyStatusLabel[s] }}</option>
                </select>
            </label>
            <label class="text-xs font-medium text-slate-600">Cari perusahaan
                <input v-model="filterForm.q" class="mt-1 block rounded-lg border-slate-300 text-sm" placeholder="Nama perusahaan" />
            </label>
            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50">Terapkan</button>
        </form>

        <p v-if="!items.length" class="mt-6 rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
            Tidak ada perusahaan pada filter ini.
        </p>
        <div v-else class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">Perusahaan</th><th class="px-4 py-3">Email Resmi</th><th class="px-4 py-3">Diajukan</th><th class="px-4 py-3">Status</th><th class="px-4 py-3" /></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="row in items" :key="row.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ row.name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ row.official_email ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ formatDateTime(row.submitted_at) }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="companyStatusBadgeClass[row.verification_status] ?? 'bg-slate-100 text-slate-700'">
                                {{ statusLabel(companyStatusLabel, row.verification_status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="`/verifikasi-perusahaan/${row.id}`" class="rounded-lg bg-[#0061a5] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#004172]">Tinjau</Link>
                        </td>
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
