<script setup lang="ts">
/**
 * Super Admin "Audit Log" (Frontend Vertical Slice v10) — the browser
 * realization of GET /audit-logs (API_CONTRACT.md Part XI, FSD §5.13
 * FR-AUD-001).
 *
 * Strictly read-only: `audit_logs` is physically append-only and the contract
 * defines no create/update/delete for any role, Super Admin included
 * (INV-016) — this page renders no action control of any kind. The list,
 * pagination and filter option sets come from SuperAdminPageController
 * (frozen AuditLogScope / ListAuditLogs / AuditLogPresenter).
 *
 * `change_summary` is redacted at write time (INV-035): it never carries a
 * password, hash, token, SMTP credential or storage key — an SMTP change
 * shows `credential_changed: true`, never a value. The page displays it
 * verbatim. `ip_address` / device metadata are never sent (H-4 unresolved).
 */
import { Head } from '@inertiajs/vue3'
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import AppShell from '@/layouts/AppShell.vue'
import { formatDateTime } from '@/lib/labels'

interface AuditRow {
    id: number
    created_at: string | null
    actor_user_id: number | null
    actor_name: string | null
    action: string
    object_type: string | null
    object_id: number | null
    correlation_id: string | null
    change_summary: Record<string, unknown> | null
}
interface Pagination { page: number; per_page: number; total: number; last_page: number }

const props = defineProps<{
    items: AuditRow[]
    pagination: Pagination
    filters: Record<string, string>
    action_options: string[]
    object_type_options: string[]
}>()

const form = reactive({
    action: props.filters.action ?? '',
    object_type: props.filters.object_type ?? '',
    actor_user_id: props.filters.actor_user_id ?? '',
    object_id: props.filters.object_id ?? '',
    correlation_id: props.filters.correlation_id ?? '',
    created_from: props.filters.created_from ?? '',
    created_to: props.filters.created_to ?? '',
})

function buildQuery(extra: Record<string, string | number> = {}) {
    const q: Record<string, string | number> = { ...extra }
    for (const [k, v] of Object.entries(form)) {
        if (v !== '' && v !== null && v !== undefined) q[k] = v as string
    }
    return q
}

function applyFilters() {
    router.get('/audit-log', buildQuery(), { preserveState: true, replace: true })
}

function resetFilters() {
    for (const k of Object.keys(form)) (form as Record<string, string>)[k] = ''
    router.get('/audit-log', {}, { preserveState: true, replace: true })
}

function goToPage(page: number) {
    router.get('/audit-log', buildQuery({ page }), { preserveState: true, replace: true })
}

function summaryPairs(summary: Record<string, unknown> | null): Array<[string, string]> {
    if (!summary || typeof summary !== 'object') return []
    return Object.entries(summary).map(([k, v]) => [k, typeof v === 'object' ? JSON.stringify(v) : String(v)])
}
</script>

<template>
    <Head title="Audit Log" />
    <AppShell persona="super-admin" active="audit-log" title="Audit Log">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Audit Log</h1>
        <p class="mt-2 max-w-2xl text-slate-600">
            Jejak aktivitas sistem — bersifat hanya-baca dan <span class="font-semibold">append-only</span>.
            Tidak ada tindakan ubah atau hapus untuk peran apa pun.
        </p>

        <form class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="applyFilters">
            <label class="text-xs font-medium text-slate-600">Aksi
                <select v-model="form.action" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    <option value="">Semua aksi</option>
                    <option v-for="a in action_options" :key="a" :value="a">{{ a }}</option>
                </select>
            </label>
            <label class="text-xs font-medium text-slate-600">Jenis objek
                <select v-model="form.object_type" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    <option value="">Semua jenis</option>
                    <option v-for="o in object_type_options" :key="o" :value="o">{{ o }}</option>
                </select>
            </label>
            <label class="text-xs font-medium text-slate-600">ID Aktor
                <input v-model="form.actor_user_id" inputmode="numeric" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" placeholder="mis. 12" />
            </label>
            <label class="text-xs font-medium text-slate-600">ID Objek
                <input v-model="form.object_id" inputmode="numeric" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" placeholder="mis. 340" />
            </label>
            <label class="text-xs font-medium text-slate-600">Correlation ID
                <input v-model="form.correlation_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
            </label>
            <label class="text-xs font-medium text-slate-600">Dari tanggal
                <input v-model="form.created_from" type="date" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
            </label>
            <label class="text-xs font-medium text-slate-600">Sampai tanggal
                <input v-model="form.created_to" type="date" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
            </label>
            <div class="flex items-end gap-2">
                <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50">Terapkan</button>
                <button type="button" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-500 hover:text-slate-700" @click="resetFilters">Reset</button>
            </div>
        </form>

        <p class="mt-4 text-xs text-slate-500">{{ pagination.total }} entri</p>

        <div v-if="!items.length" class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">
            Tidak ada entri audit yang cocok.
        </div>
        <div v-else class="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3">Aktor</th>
                        <th class="px-4 py-3">Aksi</th>
                        <th class="px-4 py-3">Objek</th>
                        <th class="px-4 py-3">Correlation</th>
                        <th class="px-4 py-3">Ringkasan Perubahan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="row in items" :key="row.id" class="align-top hover:bg-slate-50">
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ formatDateTime(row.created_at) }}</td>
                        <td class="px-4 py-3 text-[#002045]">
                            <span v-if="row.actor_user_id">{{ row.actor_name ?? 'Pengguna' }} <span class="text-slate-400">#{{ row.actor_user_id }}</span></span>
                            <span v-else class="text-slate-400">Sistem</span>
                        </td>
                        <td class="px-4 py-3"><span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-700">{{ row.action }}</span></td>
                        <td class="px-4 py-3 text-slate-600">
                            <span v-if="row.object_type">{{ row.object_type }}<span v-if="row.object_id" class="text-slate-400"> #{{ row.object_id }}</span></span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ row.correlation_id ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <ul v-if="summaryPairs(row.change_summary).length" class="space-y-0.5 text-xs">
                                <li v-for="[k, v] in summaryPairs(row.change_summary)" :key="k">
                                    <span class="font-medium text-slate-600">{{ k }}:</span> <span class="text-slate-500">{{ v }}</span>
                                </li>
                            </ul>
                            <span v-else class="text-xs text-slate-400">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="pagination.last_page > 1" class="mt-6 flex items-center justify-between text-sm">
            <button type="button" :disabled="pagination.page <= 1"
                class="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold text-slate-700 disabled:opacity-40"
                @click="goToPage(pagination.page - 1)">Sebelumnya</button>
            <span class="text-slate-500">Halaman {{ pagination.page }} dari {{ pagination.last_page }}</span>
            <button type="button" :disabled="pagination.page >= pagination.last_page"
                class="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold text-slate-700 disabled:opacity-40"
                @click="goToPage(pagination.page + 1)">Berikutnya</button>
        </nav>
    </AppShell>
</template>
