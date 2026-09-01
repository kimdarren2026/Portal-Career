<script setup lang="ts">
/**
 * Career Center "Data Perusahaan" (Frontend Vertical Slice v9) — read-only
 * company reference directory. Server-scoped (`CompanyScope`, global reader),
 * paginated. NO review actions — those live in "Verifikasi Perusahaan".
 */
import { Head, Link, router } from '@inertiajs/vue3'
import { reactive } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { companyStatusBadgeClass, companyStatusLabel, formatDateTime, statusLabel } from '@/lib/labels'

interface CompanyRow {
    id: number; name: string; verification_status: string
    official_email: string | null; verified_at: string | null; updated_at: string | null
}
const props = defineProps<{
    items: CompanyRow[]
    pagination: { page: number; per_page: number; total: number; last_page: number }
    filters: Record<string, string>
}>()

const statusOptions = Object.keys(companyStatusLabel)
const form = reactive({ status: props.filters.status ?? '', q: props.filters.q ?? '' })

function applyFilters() {
    const query: Record<string, string> = {}
    if (form.status) query.status = form.status
    if (form.q) query.q = form.q
    router.get('/data-perusahaan', query, { preserveState: true, replace: true })
}
function goToPage(page: number) {
    router.get('/data-perusahaan', { ...form, page }, { preserveState: true, replace: true })
}
</script>

<template>
    <Head title="Data Perusahaan" />
    <AppShell persona="career-center" active="data-perusahaan" title="Data Perusahaan">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Data Perusahaan</h1>
        <p class="mt-2 max-w-2xl text-slate-600">Direktori seluruh perusahaan terdaftar untuk rujukan. Tindakan verifikasi dilakukan pada halaman Verifikasi Perusahaan.</p>

        <form class="mt-6 flex flex-wrap items-end gap-3" @submit.prevent="applyFilters">
            <label class="text-xs font-medium text-slate-600">Status
                <select v-model="form.status" class="mt-1 block rounded-lg border-slate-300 text-sm">
                    <option value="">Semua status</option>
                    <option v-for="s in statusOptions" :key="s" :value="s">{{ companyStatusLabel[s] }}</option>
                </select>
            </label>
            <label class="text-xs font-medium text-slate-600">Cari perusahaan
                <input v-model="form.q" class="mt-1 block rounded-lg border-slate-300 text-sm" placeholder="Nama perusahaan" />
            </label>
            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50">Terapkan</button>
        </form>

        <div v-if="!items.length" class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">Tidak ada perusahaan yang cocok.</div>
        <div v-else class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-3">Perusahaan</th><th class="px-4 py-3">Status Verifikasi</th><th class="px-4 py-3">Email Resmi</th><th class="px-4 py-3">Diperbarui</th><th class="px-4 py-3"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="c in items" :key="c.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-[#002045]">
                            <Link :href="`/data-perusahaan/${c.id}`" class="hover:underline">{{ c.name }}</Link>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="companyStatusBadgeClass[c.verification_status] ?? 'bg-slate-100 text-slate-700'">
                                {{ statusLabel(companyStatusLabel, c.verification_status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ c.official_email ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ formatDateTime(c.updated_at) }}</td>
                        <td class="px-4 py-3 text-right"><Link :href="`/data-perusahaan/${c.id}`" class="font-medium text-[#0061a5] hover:underline">Detail</Link></td>
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
