<script setup lang="ts">
/**
 * Super Admin "Program Studi" — READ_ONLY_REFERENCE.
 *
 * A focused view over the `study-programs` master-data collection
 * (`GET /admin/master-data/study-programs`). Real rows only; no programme is
 * fabricated. Writes stay DEFERRED (DF-1).
 */
import { Head } from '@inertiajs/vue3'
import AppShell from '@/layouts/AppShell.vue'

interface Program {
    id: number
    code: string | null
    name: string
    organizational_unit_id: number | null
    active: boolean
}
interface Result { rows: Program[]; total: number; active_total: number }
defineProps<{ result: Result }>()
</script>

<template>
    <Head title="Program Studi" />
    <AppShell persona="super-admin" active="program-studi" title="Program Studi">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Program Studi</h1>
        <p class="mt-2 max-w-2xl text-slate-600">
            Referensi program studi untuk verifikasi kandidat, riwayat pendidikan, dan persyaratan lowongan.
            Bersifat <span class="font-semibold">hanya-baca</span>; perubahan ditangguhkan (DF-1).
        </p>

        <p class="mt-4 text-xs text-slate-500">{{ result.total }} program · {{ result.active_total }} aktif</p>

        <div v-if="!result.rows.length" class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">
            Belum ada program studi yang di-seed oleh institusi.
        </div>
        <div v-else class="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nama Program</th>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Unit</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="p in result.rows" :key="p.id" class="hover:bg-slate-50" :class="p.active ? '' : 'text-slate-400'">
                        <td class="px-4 py-3 font-medium text-[#002045]">{{ p.name }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ p.code ?? '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ p.organizational_unit_id ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs">{{ p.active ? 'Aktif' : 'Nonaktif' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppShell>
</template>
