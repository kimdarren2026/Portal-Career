<script setup lang="ts">
/** Admin Kepegawaian — campus applicant list (v8). */
import { Head, Link, router } from '@inertiajs/vue3'
import { reactive } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { applicationStatusBadgeClass, applicationStatusLabel, formatDateTime, statusLabel } from '@/lib/labels'

interface Row {
    id: number; application_code: string; vacancy_id: number; vacancy_title: string | null
    current_status: string; first_applied_at: string | null; candidate: { name?: string; headline?: string }
}
const props = defineProps<{
    items: Row[]; pagination: { page: number; last_page: number }
    filters: Record<string, string>; vacancies: { id: number; title: string }[]
}>()
const form = reactive({ vacancy_id: props.filters.vacancy_id ?? '', current_status: props.filters.current_status ?? '' })

function applyFilter() {
    const q: Record<string, string> = {}
    if (form.vacancy_id) q.vacancy_id = form.vacancy_id
    if (form.current_status) q.current_status = form.current_status
    router.get('/kepegawaian/pelamar', q, { preserveState: true, replace: true })
}
function goToPage(page: number) {
    router.get('/kepegawaian/pelamar', { ...form, page }, { preserveState: true, replace: true })
}
</script>

<template>
    <Head title="Pelamar" />
    <AppShell persona="admin-kepegawaian" active="kepegawaian/pelamar" title="Pelamar">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Pelamar</h1>
        <p class="mt-2 max-w-2xl text-slate-600">Pelamar pada lowongan Karier di Kampus.</p>

        <form class="mt-6 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4" @submit.prevent="applyFilter">
            <label class="text-sm font-medium text-slate-700">Lowongan
                <select v-model="form.vacancy_id" class="mt-1 block min-w-48 rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]">
                    <option value="">Semua Lowongan</option>
                    <option v-for="v in vacancies" :key="v.id" :value="v.id">{{ v.title }}</option>
                </select>
            </label>
            <label class="text-sm font-medium text-slate-700">Status
                <select v-model="form.current_status" class="mt-1 block rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]">
                    <option value="">Semua Status</option>
                    <option v-for="(label, code) in applicationStatusLabel" :key="code" :value="code">{{ label }}</option>
                </select>
            </label>
            <button type="submit" class="rounded-lg bg-[#0061a5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#004172]">Terapkan</button>
        </form>

        <div v-if="!items.length" class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">Belum ada pelamar.</div>
        <div v-else class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">Kandidat</th><th class="px-4 py-3">Lowongan</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Diajukan</th><th class="px-4 py-3"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="item in items" :key="item.id">
                        <td class="px-4 py-3"><p class="font-medium text-slate-800">{{ item.candidate.name ?? '—' }}</p><p class="text-xs text-slate-500">{{ item.candidate.headline ?? '' }}</p></td>
                        <td class="px-4 py-3 text-slate-600">{{ item.vacancy_title ?? `#${item.vacancy_id}` }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-3 py-1 text-xs font-semibold" :class="applicationStatusBadgeClass[item.current_status] ?? 'bg-slate-100 text-slate-700'">{{ statusLabel(applicationStatusLabel, item.current_status) }}</span></td>
                        <td class="px-4 py-3 text-slate-600">{{ formatDateTime(item.first_applied_at) }}</td>
                        <td class="px-4 py-3 text-right"><Link :href="`/kepegawaian/pelamar/${item.id}`" class="font-medium text-[#0061a5] hover:underline">Detail</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="pagination.last_page > 1" class="mt-6 flex items-center justify-center gap-2">
            <button v-for="page in pagination.last_page" :key="page" type="button"
                class="h-9 w-9 rounded-lg text-sm font-medium" :class="page === pagination.page ? 'bg-[#0061a5] text-white' : 'bg-white text-slate-700 hover:bg-slate-100'"
                @click="goToPage(page)">{{ page }}</button>
        </nav>
    </AppShell>
</template>
