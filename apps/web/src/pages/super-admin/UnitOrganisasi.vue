<script setup lang="ts">
/**
 * Super Admin "Unit Organisasi" — READ_ONLY_REFERENCE.
 *
 * A focused view over the `organizational-units` master-data collection
 * (`GET /admin/master-data/organizational-units`). Real rows only, rendered as
 * a parent/child tree via `parent_unit_id`. No unit is seeded or fabricated
 * here; writes stay DEFERRED (DF-1).
 */
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppShell from '@/layouts/AppShell.vue'

interface Unit { id: number; parent_unit_id: number | null; code: string | null; name: string; active: boolean }
interface Result { rows: Unit[]; total: number; active_total: number }
const props = defineProps<{ result: Result }>()

interface Node extends Unit { depth: number }

const ordered = computed<Node[]>(() => {
    const byParent = new Map<number | null, Unit[]>()
    for (const u of props.result.rows) {
        const key = u.parent_unit_id
        if (!byParent.has(key)) byParent.set(key, [])
        byParent.get(key)!.push(u)
    }
    const known = new Set(props.result.rows.map((u) => u.id))
    const out: Node[] = []
    const walk = (parent: number | null, depth: number) => {
        for (const u of byParent.get(parent) ?? []) {
            out.push({ ...u, depth })
            walk(u.id, depth + 1)
        }
    }
    walk(null, 0)
    // Orphans whose parent is not in the current set — surface them at root.
    for (const u of props.result.rows) {
        if (u.parent_unit_id !== null && !known.has(u.parent_unit_id) && !out.some((n) => n.id === u.id)) {
            out.push({ ...u, depth: 0 })
        }
    }
    return out
})
</script>

<template>
    <Head title="Unit Organisasi" />
    <AppShell persona="super-admin" active="unit-organisasi" title="Unit Organisasi">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Unit Organisasi</h1>
        <p class="mt-2 max-w-2xl text-slate-600">
            Struktur unit organisasi kampus (fakultas / bagian / divisi) untuk kepemilikan lowongan internal.
            Bersifat <span class="font-semibold">hanya-baca</span>; perubahan ditangguhkan (DF-1).
        </p>

        <p class="mt-4 text-xs text-slate-500">{{ result.total }} unit · {{ result.active_total }} aktif</p>

        <div v-if="!ordered.length" class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">
            Belum ada unit organisasi yang di-seed oleh institusi.
        </div>
        <div v-else class="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nama Unit</th>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="n in ordered" :key="n.id" class="hover:bg-slate-50" :class="n.active ? '' : 'text-slate-400'">
                        <td class="px-4 py-3">
                            <span :style="{ paddingLeft: n.depth * 20 + 'px' }" class="inline-block font-medium text-[#002045]">
                                <span v-if="n.depth" class="text-slate-300">└ </span>{{ n.name }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ n.code ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs">{{ n.active ? 'Aktif' : 'Nonaktif' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppShell>
</template>
