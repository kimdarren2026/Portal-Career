<script setup lang="ts">
/**
 * Super Admin "Master Data" — READ_ONLY_REFERENCE.
 *
 * The read side of `GET /api/v1/admin/master-data/{collection}` for the six
 * frozen collections. Active and inactive rows are shown, using exactly the
 * columns `DATA_DICTIONARY.md` defines. Write operations (create / update /
 * deactivate) stay DEFERRED beyond MVP (`API_SIZE_REVIEW.md` DF-1) — there is
 * deliberately no Tambah / Ubah / Hapus control.
 */
import { Head, router } from '@inertiajs/vue3'
import AppShell from '@/layouts/AppShell.vue'
import { formatDateTime } from '@/lib/labels'

interface Result {
    collection: string
    hierarchical: boolean
    columns: string[]
    rows: Array<Record<string, unknown>>
    total: number
    active_total: number
}
const props = defineProps<{ collections: string[]; result: Result }>()

const collectionLabel: Record<string, string> = {
    'organizational-units': 'Unit Organisasi',
    'study-programs': 'Program Studi',
    industries: 'Industri',
    'organization-types': 'Jenis Organisasi',
    skills: 'Keahlian',
    'geographic-areas': 'Wilayah Geografis',
}

const columnLabel: Record<string, string> = {
    id: 'ID',
    parent_unit_id: 'Induk',
    parent_geographic_area_id: 'Induk',
    organizational_unit_id: 'Unit',
    code: 'Kode',
    name: 'Nama',
    normalized_name: 'Nama Ternormalisasi',
    description: 'Deskripsi',
    area_type: 'Tipe',
    active: 'Aktif',
    created_at: 'Dibuat',
    updated_at: 'Diperbarui',
}

function open(collection: string) {
    router.get('/master-data', { collection }, { preserveState: true, replace: true })
}

function cell(row: Record<string, unknown>, col: string): string {
    const v = row[col]
    if (col === 'active') return v ? 'Ya' : 'Tidak'
    if (col === 'created_at' || col === 'updated_at') return formatDateTime(v as string | null)
    if (v === null || v === undefined || v === '') return '—'
    return String(v)
}
</script>

<template>
    <Head title="Master Data" />
    <AppShell persona="super-admin" active="master-data" title="Master Data">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Master Data</h1>
        <p class="mt-2 max-w-2xl text-slate-600">
            Referensi data induk institusi. Bersifat <span class="font-semibold">hanya-baca</span> untuk MVP —
            operasi tambah/ubah/nonaktif ditangguhkan (DF-1).
        </p>

        <div class="mt-6 flex flex-wrap gap-2">
            <button
                v-for="c in collections"
                :key="c"
                type="button"
                class="rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors"
                :class="c === result.collection ? 'border-[#0061a5] bg-[#0061a5] text-white' : 'border-slate-300 text-slate-700 hover:border-[#0061a5]'"
                @click="open(c)"
            >
                {{ collectionLabel[c] ?? c }}
            </button>
        </div>

        <p class="mt-4 text-xs text-slate-500">{{ result.total }} baris · {{ result.active_total }} aktif</p>

        <div v-if="!result.rows.length" class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">
            Belum ada data pada koleksi ini.
        </div>
        <div v-else class="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th v-for="col in result.columns" :key="col" class="px-4 py-3">{{ columnLabel[col] ?? col }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="(row, i) in result.rows" :key="i" class="align-top hover:bg-slate-50" :class="row.active ? '' : 'text-slate-400'">
                        <td v-for="col in result.columns" :key="col" class="px-4 py-3" :class="col === 'id' || col.endsWith('_id') ? 'font-mono text-xs' : ''">
                            {{ cell(row, col) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppShell>
</template>
