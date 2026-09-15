<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppShell from '@/layouts/AppShell.vue'

type Partnership = {
    id: number
    company_id: number
    company_name: string
    partnership_type: string
    agreement_number?: string | null
    start_date: string
    end_date?: string | null
    status: string
    currently_active: boolean
    campus_pic?: string | null
    company_pic?: string | null
    document_available: boolean
    notes?: string | null
}

const props = defineProps<{
    items: Partnership[]
    pagination: { page: number; total: number; last_page: number }
    by_status?: Record<string, number>
    active_count: number
}>()

const statusRows = computed(() => Object.entries(props.by_status ?? {}).sort((a, b) => b[1] - a[1]))
</script>

<template>
    <Head title="Kemitraan" />
    <AppShell persona="career-center" active="kemitraan" title="Kemitraan">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="page-eyebrow">Perusahaan mitra</p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight text-[#002045]">Kemitraan</h1>
                <p class="mt-2 max-w-2xl text-slate-600">Daftar read-only kerja sama kampus dan perusahaan yang sudah tercatat.</p>
            </div>
            <Link href="/data-perusahaan" class="text-sm font-semibold text-[#0061a5] hover:underline">Lihat data perusahaan →</Link>
        </div>

        <section class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" aria-label="Ringkasan kemitraan">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-slate-600">Total Kemitraan</p><p class="mt-2 text-4xl font-bold text-[#002045]">{{ pagination.total }}</p></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-slate-600">Mitra Aktif Saat Ini</p><p class="mt-2 text-4xl font-bold text-[#002045]">{{ active_count }}</p></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-semibold text-slate-600">Status Tercatat</p><div class="mt-3 flex flex-wrap gap-2"><span v-for="entry in statusRows" :key="entry[0]" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ entry[0] }} · {{ entry[1] }}</span><span v-if="statusRows.length === 0" class="text-sm text-slate-500">Belum ada status.</span></div></div>
        </section>

        <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div v-if="items.length === 0" class="px-6 py-16 text-center">
                <p class="text-lg font-bold text-[#002045]">Belum ada kemitraan</p>
                <p class="mt-2 text-sm text-slate-500">Data kemitraan akan muncul di sini setelah tercatat di sistem.</p>
            </div>
            <div v-else class="divide-y divide-slate-100">
                <article v-for="item in items" :key="item.id" class="p-5 sm:p-6">
                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-bold text-[#002045]">{{ item.company_name }}</h2>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="item.currently_active ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-700'">{{ item.currently_active ? 'Mitra Aktif' : item.status }}</span>
                            </div>
                            <p class="mt-1 text-sm text-slate-600">{{ item.partnership_type }}<span v-if="item.agreement_number"> · {{ item.agreement_number }}</span></p>
                        </div>
                        <Link :href="`/data-perusahaan/${item.company_id}`" class="text-sm font-semibold text-[#0061a5] hover:underline">Detail perusahaan →</Link>
                    </div>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div><dt class="text-slate-500">Periode</dt><dd class="mt-1 font-semibold text-slate-800">{{ item.start_date }} — {{ item.end_date ?? 'Tanpa batas akhir' }}</dd></div>
                        <div><dt class="text-slate-500">PIC Kampus</dt><dd class="mt-1 font-semibold text-slate-800">{{ item.campus_pic ?? '-' }}</dd></div>
                        <div><dt class="text-slate-500">PIC Perusahaan</dt><dd class="mt-1 font-semibold text-slate-800">{{ item.company_pic ?? '-' }}</dd></div>
                        <div><dt class="text-slate-500">Dokumen</dt><dd class="mt-1 font-semibold text-slate-800">{{ item.document_available ? 'Tercatat' : 'Belum tersedia' }}</dd></div>
                    </dl>
                    <p v-if="item.notes" class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">{{ item.notes }}</p>
                </article>
            </div>
        </section>

        <nav v-if="pagination.last_page > 1" class="mt-5 flex items-center justify-between" aria-label="Paginasi kemitraan">
            <Link v-if="pagination.page > 1" :href="`/kemitraan?page=${pagination.page - 1}`" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">← Sebelumnya</Link><span v-else />
            <span class="text-sm text-slate-500">Halaman {{ pagination.page }} dari {{ pagination.last_page }}</span>
            <Link v-if="pagination.page < pagination.last_page" :href="`/kemitraan?page=${pagination.page + 1}`" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Berikutnya →</Link><span v-else />
        </nav>
    </AppShell>
</template>
