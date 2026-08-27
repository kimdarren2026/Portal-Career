<script setup lang="ts">
/** Mirrors design/stitch/candidate/lamaran-saya's card-list structure — not a pixel reproduction. */
import { Head, Link, router } from '@inertiajs/vue3'
import { reactive } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { applicationStatusBadgeClass, applicationStatusLabel, formatDateTime, statusLabel } from '@/lib/labels'

interface ApplicationRow {
    id: number
    application_code: string
    vacancy_id: number
    vacancy_title: string | null
    current_status: string
    first_applied_at: string | null
}

interface Pagination { page: number; per_page: number; total: number; last_page: number }

const props = defineProps<{ items: ApplicationRow[]; pagination: Pagination; filters: Record<string, string> }>()

const form = reactive({ current_status: props.filters.current_status ?? '' })

function applyFilter() {
    const query: Record<string, string> = {}
    if (form.current_status) query.current_status = form.current_status
    router.get('/lamaran-saya', query, { preserveState: true, replace: true })
}

function goToPage(page: number) {
    router.get('/lamaran-saya', { ...form, page }, { preserveState: true, replace: true })
}
</script>

<template>
    <Head title="Lamaran Saya" />
    <AppShell persona="candidate" active="lamaran-saya" title="Lamaran Saya">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Lamaran Saya</h1>
                <p class="mt-2 max-w-2xl text-slate-600">Riwayat lengkap lamaran yang pernah Anda kirimkan melalui portal.</p>
            </div>
        </div>

        <form class="mt-6 flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white p-4" @submit.prevent="applyFilter">
            <label class="text-sm font-medium text-slate-700">
                Status
                <select v-model="form.current_status" class="mt-1 block rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]">
                    <option value="">Semua Status</option>
                    <option v-for="(label, code) in applicationStatusLabel" :key="code" :value="code">{{ label }}</option>
                </select>
            </label>
            <button type="submit" class="mt-6 rounded-lg bg-[#0061a5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#004172]">Terapkan</button>
        </form>

        <div v-if="!items.length" class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
            <p class="text-lg font-semibold text-slate-700">Belum ada lamaran</p>
            <p class="mt-2 text-sm text-slate-500">Lamaran yang Anda kirimkan akan muncul di sini.</p>
            <Link href="/lowongan" class="mt-4 inline-block rounded-lg bg-[#0061a5] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#004172]">Cari Lowongan</Link>
        </div>

        <ul v-else class="mt-6 space-y-3">
            <li v-for="item in items" :key="item.id">
                <Link :href="`/lamaran-saya/${item.id}`" class="block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-[#0061a5]">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-slate-800">{{ item.vacancy_title ?? `Lowongan #${item.vacancy_id}` }}</p>
                            <p class="mt-1 text-sm text-slate-500">Kode lamaran: {{ item.application_code }} · Diajukan {{ formatDateTime(item.first_applied_at) }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="applicationStatusBadgeClass[item.current_status] ?? 'bg-slate-100 text-slate-700'">
                            {{ statusLabel(applicationStatusLabel, item.current_status) }}
                        </span>
                    </div>
                </Link>
            </li>
        </ul>

        <nav v-if="pagination.last_page > 1" class="mt-6 flex items-center justify-center gap-2" aria-label="Navigasi halaman">
            <button
                v-for="page in pagination.last_page" :key="page" type="button"
                class="h-9 w-9 rounded-lg text-sm font-medium" :class="page === pagination.page ? 'bg-[#0061a5] text-white' : 'bg-white text-slate-700 hover:bg-slate-100'"
                @click="goToPage(page)"
            >{{ page }}</button>
        </nav>
    </AppShell>
</template>
