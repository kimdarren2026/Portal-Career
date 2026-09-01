<script setup lang="ts">
/** Admin Kepegawaian — Lowongan Kampus list (v8). Campus vacancies only. */
import { Head, Link, router } from '@inertiajs/vue3'
import { reactive } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { formatDateTime, statusLabel, vacancyStatusBadgeClass, vacancyStatusLabel } from '@/lib/labels'

interface Row { id: number; title: string; current_status: string; open_at: string | null; close_at: string | null }
interface Pagination { page: number; per_page: number; total: number; last_page: number }

const props = defineProps<{ items: Row[]; pagination: Pagination; filters: Record<string, string> }>()
const form = reactive({ status: props.filters.status ?? '', q: props.filters.q ?? '' })

function applyFilter() {
    const query: Record<string, string> = {}
    if (form.status) query.status = form.status
    if (form.q) query.q = form.q
    router.get('/kepegawaian/lowongan-kampus', query, { preserveState: true, replace: true })
}
function goToPage(page: number) {
    router.get('/kepegawaian/lowongan-kampus', { ...form, page }, { preserveState: true, replace: true })
}
</script>

<template>
    <Head title="Lowongan Kampus" />
    <AppShell persona="admin-kepegawaian" active="kepegawaian/lowongan-kampus" title="Lowongan Kampus">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Lowongan Kampus</h1>
            <Link href="/kepegawaian/lowongan-kampus/baru" class="rounded-lg bg-[#0061a5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#004172]">
                + Lowongan Baru
            </Link>
        </div>
        <p class="mt-2 max-w-2xl text-slate-600">Lowongan Karier di Kampus. Publikasi langsung tanpa moderasi Career Center.</p>

        <form class="mt-6 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4" @submit.prevent="applyFilter">
            <label class="text-sm font-medium text-slate-700">Status
                <select v-model="form.status" class="mt-1 block min-w-44 rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]">
                    <option value="">Semua Status</option>
                    <option v-for="s in ['DRAFT','SCHEDULED','PUBLISHED','CLOSED','EXPIRED','SUSPENDED']" :key="s" :value="s">
                        {{ statusLabel(vacancyStatusLabel, s) }}
                    </option>
                </select>
            </label>
            <label class="text-sm font-medium text-slate-700">Cari
                <input v-model="form.q" class="mt-1 block rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]" placeholder="Judul lowongan" />
            </label>
            <button type="submit" class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200">Terapkan</button>
        </form>

        <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr><th class="px-5 py-3">Judul</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Periode</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="row in items" :key="row.id" class="hover:bg-slate-50">
                        <td class="px-5 py-4 font-medium text-[#002045]">
                            <Link :href="`/kepegawaian/lowongan-kampus/${row.id}`" class="hover:underline">{{ row.title }}</Link>
                        </td>
                        <td class="px-5 py-4">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="vacancyStatusBadgeClass[row.current_status] ?? 'bg-slate-100 text-slate-700'">
                                {{ statusLabel(vacancyStatusLabel, row.current_status) }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-slate-600">{{ formatDateTime(row.open_at) }} — {{ formatDateTime(row.close_at) }}</td>
                    </tr>
                    <tr v-if="items.length === 0"><td colspan="3" class="px-5 py-10 text-center text-sm text-slate-500">Belum ada lowongan kampus.</td></tr>
                </tbody>
            </table>
        </div>

        <div v-if="pagination.last_page > 1" class="mt-6 flex items-center justify-between text-sm">
            <button type="button" :disabled="pagination.page <= 1" class="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold disabled:opacity-40" @click="goToPage(pagination.page - 1)">Sebelumnya</button>
            <span class="text-slate-500">Halaman {{ pagination.page }} / {{ pagination.last_page }}</span>
            <button type="button" :disabled="pagination.page >= pagination.last_page" class="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold disabled:opacity-40" @click="goToPage(pagination.page + 1)">Berikutnya</button>
        </div>
    </AppShell>
</template>
