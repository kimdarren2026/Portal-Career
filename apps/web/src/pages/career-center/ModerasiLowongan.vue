<script setup lang="ts">
/**
 * Career Center "Moderasi Lowongan" queue (Frontend Vertical Slice v4).
 * Follows Stitch `career-center/moderasi-lowongan`. Reuses the frozen
 * `ListVacancies` / `VacancyScope` (Career Center reads every COMPANY
 * vacancy), paginated — no global fetch filtered in the browser. Moderation
 * decisions happen on the detail page and post to the frozen
 * `POST /vacancies/{vacancy}/{action}` routes.
 */
import { Head, Link, router } from '@inertiajs/vue3'
import { reactive } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import {
    applicationMethodLabel, formatDateTime, statusLabel, targetAudienceLabel,
    vacancyStatusBadgeClass, vacancyStatusLabel, vacancyTypeLabel,
} from '@/lib/labels'

interface VacancyRow {
    id: number; title: string; vacancy_type: string | null; current_status: string | null
    target_audience: string | null; application_method: string | null
    company_id: number | null; company_name: string | null; created_at: string | null
}
interface Pagination { page: number; per_page: number; total: number; last_page: number }

const props = defineProps<{
    items: VacancyRow[]
    pagination: Pagination
    filters: Record<string, string>
}>()

const statusOptions = Object.keys(vacancyStatusLabel)
const audienceOptions = Object.keys(targetAudienceLabel)
const filterForm = reactive({
    status: props.filters.status ?? '',
    target_audience: props.filters.target_audience ?? '',
    q: props.filters.q ?? '',
})

function applyFilters() {
    const query: Record<string, string> = {}
    if (filterForm.status) query.status = filterForm.status
    if (filterForm.target_audience) query.target_audience = filterForm.target_audience
    if (filterForm.q) query.q = filterForm.q
    router.get('/moderasi-lowongan', query, { preserveState: true, replace: true })
}

function goToPage(page: number) {
    router.get('/moderasi-lowongan', { ...props.filters, page }, { preserveState: true, replace: true })
}
</script>

<template>
    <Head title="Moderasi Lowongan" />
    <AppShell persona="career-center" active="moderasi-lowongan" title="Moderasi Lowongan">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Moderasi Lowongan</h1>
            <a href="/laporan-lowongan" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-[#0061a5] hover:border-[#0061a5]">Laporan dari pengguna &rarr;</a>
        </div>
        <p class="mt-2 max-w-2xl text-slate-600">Tinjau lowongan dari perusahaan terverifikasi sebelum ditampilkan kepada kandidat.</p>

        <form class="mt-6 flex flex-wrap items-end gap-3" @submit.prevent="applyFilters">
            <label class="text-xs font-medium text-slate-600">Status
                <select v-model="filterForm.status" class="mt-1 block rounded-lg border-slate-300 text-sm">
                    <option value="">Semua status</option>
                    <option v-for="s in statusOptions" :key="s" :value="s">{{ vacancyStatusLabel[s] }}</option>
                </select>
            </label>
            <label class="text-xs font-medium text-slate-600">Target Kandidat
                <select v-model="filterForm.target_audience" class="mt-1 block rounded-lg border-slate-300 text-sm">
                    <option value="">Semua target</option>
                    <option v-for="a in audienceOptions" :key="a" :value="a">{{ targetAudienceLabel[a] }}</option>
                </select>
            </label>
            <label class="text-xs font-medium text-slate-600">Cari posisi
                <input v-model="filterForm.q" class="mt-1 block rounded-lg border-slate-300 text-sm" placeholder="Judul lowongan" />
            </label>
            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50">Terapkan</button>
        </form>

        <p v-if="!items.length" class="mt-6 rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
            Tidak ada lowongan pada filter ini.
        </p>
        <div v-else class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">Posisi</th><th class="px-4 py-3">Perusahaan</th><th class="px-4 py-3">Target</th><th class="px-4 py-3">Metode</th><th class="px-4 py-3">Status</th><th class="px-4 py-3" /></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="row in items" :key="row.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-800">{{ row.title }}</p>
                            <p class="text-xs text-slate-500">{{ statusLabel(vacancyTypeLabel, row.vacancy_type) }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ row.company_name ?? `#${row.company_id}` }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ statusLabel(targetAudienceLabel, row.target_audience) }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ statusLabel(applicationMethodLabel, row.application_method) }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="vacancyStatusBadgeClass[row.current_status ?? ''] ?? 'bg-slate-100 text-slate-700'">
                                {{ statusLabel(vacancyStatusLabel, row.current_status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="`/moderasi-lowongan/${row.id}`" class="rounded-lg bg-[#0061a5] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#004172]">Tinjau</Link>
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
