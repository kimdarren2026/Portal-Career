<script setup lang="ts">
/**
 * Career Center — Laporan Lowongan review queue (PGC-V1 / PD-C).
 * NEW → UNDER_REVIEW → ACTIONED | DISMISSED. Career Center staff/manager
 * transition; Super Admin reads only (`can_transition = false`).
 */
import { Head } from '@inertiajs/vue3'
import { reactive, ref, onMounted } from 'vue'
import AppShell from '@/layouts/AppShell.vue'

interface ReasonOption { code: string; label: string }
interface ReportRow {
    id: number
    vacancy: { id: number; slug: string; title: string } | null
    reason: string
    reason_label: string
    details: string | null
    status: string
    reporter: 'AUTHENTICATED' | 'ANONYMOUS'
    anonymous_contact: Record<string, string> | null
    created_at: string | null
    resolution_note: string | null
}

const props = defineProps<{ reasons: ReasonOption[]; can_transition: boolean }>()

const rows = ref<ReportRow[]>([])
const pagination = reactive({ page: 1, per_page: 25, total: 0, last_page: 1 })
const filters = reactive({ status: '', reason: '' })
const busy = ref(false)
const err = ref('')
const statusLabels: Record<string, string> = {
    NEW: 'Baru', UNDER_REVIEW: 'Sedang Ditinjau', ACTIONED: 'Ditindaklanjuti', DISMISSED: 'Ditutup',
}

async function load(page = 1) {
    busy.value = true
    err.value = ''
    const qs = new URLSearchParams()
    if (filters.status) qs.set('status', filters.status)
    if (filters.reason) qs.set('reason', filters.reason)
    if (page > 1) qs.set('page', String(page))
    const response = await fetch(`/moderasi-lowongan/laporan/data?${qs.toString()}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    const payload = (await response.json()) as {
        data?: { items: ReportRow[]; pagination: typeof pagination }
        error?: { message?: string }
    }
    busy.value = false
    if (!response.ok || !payload.data) {
        err.value = payload.error?.message ?? 'Gagal memuat laporan.'
        return
    }
    rows.value = payload.data.items
    Object.assign(pagination, payload.data.pagination)
}

async function transition(row: ReportRow, kind: 'review' | 'action' | 'dismiss') {
    let note: string | null = null
    if (kind !== 'review') {
        note = window.prompt(`Catatan (opsional) untuk ${kind === 'action' ? 'tindak lanjut' : 'penutupan'}:`) || null
    }
    const path = kind === 'review'
        ? `/vacancy-reports/${row.id}/review`
        : `/vacancy-reports/${row.id}/${kind}`
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
    busy.value = true
    err.value = ''
    const response = await fetch(path, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json', 'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify(note ? { note } : {}),
    })
    const payload = (await response.json()) as { error?: { message?: string } }
    busy.value = false
    if (!response.ok) {
        err.value = payload.error?.message ?? 'Gagal memperbarui laporan.'
        return
    }
    await load(pagination.page)
}

onMounted(() => load(1))
</script>

<template>
    <Head title="Laporan Lowongan" />
    <AppShell persona="career-center" active="moderasi-lowongan" title="Laporan Lowongan">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Laporan Lowongan</h1>
        <p class="mt-2 max-w-2xl text-slate-600">
            Laporan penyalahgunaan dari pengguna publik dan terautentikasi.
            <span v-if="!props.can_transition">Anda dapat meninjau laporan (baca-saja).</span>
        </p>

        <div class="mt-5 flex flex-wrap items-end gap-3">
            <label class="text-sm font-medium text-slate-700">Status
                <select v-model="filters.status" class="mt-1 block w-48 rounded-lg border-slate-300 text-sm">
                    <option value="">Semua</option>
                    <option value="NEW">Baru</option>
                    <option value="UNDER_REVIEW">Sedang Ditinjau</option>
                    <option value="ACTIONED">Ditindaklanjuti</option>
                    <option value="DISMISSED">Ditutup</option>
                </select>
            </label>
            <label class="text-sm font-medium text-slate-700">Alasan
                <select v-model="filters.reason" class="mt-1 block w-56 rounded-lg border-slate-300 text-sm">
                    <option value="">Semua</option>
                    <option v-for="r in reasons" :key="r.code" :value="r.code">{{ r.label }}</option>
                </select>
            </label>
            <button :disabled="busy" class="rounded-lg bg-[#0061a5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#004f87] disabled:opacity-50" @click="load(1)">Terapkan</button>
        </div>

        <p v-if="err" class="mt-3 text-sm text-[#93000a]" role="alert">{{ err }}</p>

        <section class="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Lowongan</th>
                        <th class="px-4 py-3">Alasan</th>
                        <th class="px-4 py-3">Pelapor</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="row in rows" :key="row.id" class="align-top hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a v-if="row.vacancy" :href="`/lowongan/${row.vacancy.slug}`" class="font-medium text-[#0061a5] hover:underline">{{ row.vacancy.title }}</a>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-[#002045]">{{ row.reason_label }}</div>
                            <div v-if="row.details" class="mt-1 max-w-md whitespace-pre-wrap text-xs text-slate-500">{{ row.details }}</div>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            {{ row.reporter === 'AUTHENTICATED' ? 'Terautentikasi' : 'Anonim' }}
                        </td>
                        <td class="px-4 py-3">{{ statusLabels[row.status] ?? row.status }}</td>
                        <td class="px-4 py-3">
                            <div v-if="props.can_transition && (row.status === 'NEW' || row.status === 'UNDER_REVIEW')" class="flex flex-col gap-1">
                                <button v-if="row.status === 'NEW'" :disabled="busy" class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 disabled:opacity-40" @click="transition(row, 'review')">Mulai tinjau</button>
                                <button :disabled="busy" class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-emerald-700 disabled:opacity-40" @click="transition(row, 'action')">Tindak lanjuti</button>
                                <button :disabled="busy" class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-[#93000a] disabled:opacity-40" @click="transition(row, 'dismiss')">Tutup</button>
                            </div>
                            <span v-else class="text-xs text-slate-400">—</span>
                        </td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">Tidak ada laporan.</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
            <span>Total {{ pagination.total }} laporan</span>
            <span class="flex gap-2">
                <button :disabled="busy || pagination.page <= 1" class="rounded border border-slate-300 px-2 py-1 disabled:opacity-40" @click="load(pagination.page - 1)">Sebelumnya</button>
                <span>Halaman {{ pagination.page }} / {{ pagination.last_page }}</span>
                <button :disabled="busy || pagination.page >= pagination.last_page" class="rounded border border-slate-300 px-2 py-1 disabled:opacity-40" @click="load(pagination.page + 1)">Berikutnya</button>
            </span>
        </div>
    </AppShell>
</template>
