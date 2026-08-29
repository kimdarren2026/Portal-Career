<script setup lang="ts">
/**
 * Recruitment Outcome Foundation v1 (OC-1 / RC-2 / H-5) — recruiter surface.
 * No canonical Stitch screen exists for this concept; uses the shared
 * AppShell/card/table language. Read-only page: "Belum Dicatat" is the
 * frozen H-5 incomplete report (terminal application, no INTERNAL_APPLICATION
 * outcome yet — never auto-filled), "Sudah Dicatat" is the scoped outcome
 * list. Create/correct post to the frozen `/recruitment-outcomes` JSON routes.
 */
import { Head, Link, router } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { authRequest, errorText, newIdempotencyKey } from '@/lib/auth'
import { applicationStatusLabel, formatDateTime, internalApplicationOutcomeOptions, reportedBySourceOptions, statusLabel } from '@/lib/labels'

interface Pagination { page: number; per_page: number; total: number; last_page: number }
interface IncompleteRow { application_id: number; vacancy_id: number; current_status: string; application_code: string | null; vacancy_title: string | null }
interface RecordedRow {
    id: number; source_type: string; application_id: number | null; external_apply_event_id: number | null
    outcome: string; reported_by_source: string; confirmed_by: number | null; confirmed_at: string | null
    notes: string | null; created_at: string | null; application_code: string | null; vacancy_title: string | null
}

const props = defineProps<{
    recorded: { items: RecordedRow[]; pagination: Pagination }
    incomplete: { items: IncompleteRow[]; pagination: Pagination }
    filters: Record<string, string>
    can_write: boolean
}>()

const tab = ref<'incomplete' | 'recorded'>('incomplete')
const message = ref('')
const submitting = ref(false)

/* ---- Create outcome for an incomplete application (OC-1) ---- */
const createFor = ref<number | null>(null)
const createForm = reactive({ outcome: '', reported_by_source: '', notes: '' })
function openCreate(applicationId: number) {
    createFor.value = applicationId
    createForm.outcome = ''
    createForm.reported_by_source = ''
    createForm.notes = ''
}
async function submitCreate(applicationId: number) {
    if (!createForm.outcome || !createForm.reported_by_source) return
    submitting.value = true
    message.value = ''
    const { response, payload } = await authRequest('/recruitment-outcomes', {
        source_type: 'INTERNAL_APPLICATION',
        application_id: applicationId,
        outcome: createForm.outcome,
        reported_by_source: createForm.reported_by_source,
        notes: createForm.notes || undefined,
    }, 'POST', { 'Idempotency-Key': newIdempotencyKey() })
    submitting.value = false
    if (!response.ok) { message.value = errorText(payload); return }
    createFor.value = null
    router.reload()
}

/* ---- Correct an existing outcome (mutable: outcome, reported_by_source, notes) ---- */
const editFor = ref<number | null>(null)
const editForm = reactive({ outcome: '', reported_by_source: '', notes: '' })
function openEdit(row: RecordedRow) {
    editFor.value = row.id
    editForm.outcome = row.outcome
    editForm.reported_by_source = row.reported_by_source
    editForm.notes = row.notes ?? ''
}
async function submitEdit(id: number) {
    submitting.value = true
    message.value = ''
    const { response, payload } = await authRequest(`/recruitment-outcomes/${id}`, {
        outcome: editForm.outcome,
        reported_by_source: editForm.reported_by_source,
        notes: editForm.notes || null,
    }, 'PATCH')
    submitting.value = false
    if (!response.ok) { message.value = errorText(payload); return }
    editFor.value = null
    router.reload()
}

function goToPage(page: number) {
    router.get('/outcome-rekrutmen', { ...props.filters, page }, { preserveState: true, replace: true })
}
</script>

<template>
    <Head title="Outcome Rekrutmen" />
    <AppShell persona="recruiter" active="outcome-rekrutmen" title="Outcome Rekrutmen">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Outcome Rekrutmen</h1>
        <p class="mt-2 max-w-2xl text-slate-600">Pencatatan hasil akhir rekrutmen untuk lamaran dalam portal. "Belum dicatat" berarti hasil akhir lamaran belum direkam — bukan berarti lamaran atau kandidat bermasalah.</p>

        <p v-if="message" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-[#93000a]" role="alert">{{ message }}</p>

        <div class="mt-6 flex gap-2 border-b border-slate-200 text-sm font-semibold">
            <button type="button" class="border-b-2 px-3 py-2" :class="tab === 'incomplete' ? 'border-[#0061a5] text-[#0061a5]' : 'border-transparent text-slate-500'" @click="tab = 'incomplete'">
                Belum Dicatat ({{ incomplete.pagination.total }})
            </button>
            <button type="button" class="border-b-2 px-3 py-2" :class="tab === 'recorded' ? 'border-[#0061a5] text-[#0061a5]' : 'border-transparent text-slate-500'" @click="tab = 'recorded'">
                Sudah Dicatat ({{ recorded.pagination.total }})
            </button>
        </div>

        <!-- Belum Dicatat (H-5 incomplete) -->
        <div v-if="tab === 'incomplete'" class="mt-4">
            <p v-if="!incomplete.items.length" class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                Tidak ada lamaran berstatus akhir yang belum dicatat hasilnya.
            </p>
            <ul v-else class="space-y-3">
                <li v-for="row in incomplete.items" :key="row.application_id" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">{{ row.vacancy_title ?? `Lowongan #${row.vacancy_id}` }}</p>
                            <p class="text-xs text-slate-500">Kode lamaran {{ row.application_code ?? row.application_id }} · Status lamaran: {{ statusLabel(applicationStatusLabel, row.current_status) }}</p>
                        </div>
                        <div class="flex gap-3">
                            <Link :href="`/pelamar/${row.application_id}`" class="text-xs font-semibold text-[#0061a5] hover:underline">Lihat pelamar</Link>
                            <button v-if="can_write && createFor !== row.application_id" type="button" class="rounded-lg bg-[#0061a5] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#004172]" @click="openCreate(row.application_id)">Rekam Outcome</button>
                        </div>
                    </div>
                    <form v-if="createFor === row.application_id" class="mt-4 space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-4" @submit.prevent="submitCreate(row.application_id)">
                        <label class="block text-xs font-medium text-slate-700">Outcome
                            <select v-model="createForm.outcome" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                                <option value="" disabled>Pilih outcome</option>
                                <option v-for="code in internalApplicationOutcomeOptions" :key="code" :value="code">{{ statusLabel(applicationStatusLabel, code) }}</option>
                            </select>
                        </label>
                        <label class="block text-xs font-medium text-slate-700">Dilaporkan oleh (reported_by_source)
                            <select v-model="createForm.reported_by_source" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                                <option value="" disabled>Pilih sumber</option>
                                <option v-for="src in reportedBySourceOptions" :key="src" :value="src">{{ src }}</option>
                            </select>
                        </label>
                        <label class="block text-xs font-medium text-slate-700">Catatan (opsional)<textarea v-model="createForm.notes" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" /></label>
                        <div class="flex gap-3">
                            <button type="submit" :disabled="submitting" class="rounded-lg bg-[#0061a5] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#004172] disabled:opacity-60">{{ submitting ? 'Menyimpan…' : 'Simpan Outcome' }}</button>
                            <button type="button" class="text-xs font-medium text-slate-600 hover:underline" @click="createFor = null">Batal</button>
                        </div>
                    </form>
                </li>
            </ul>
            <div v-if="incomplete.pagination.last_page > 1" class="mt-4 flex gap-2">
                <button type="button" :disabled="incomplete.pagination.page <= 1" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm disabled:opacity-40" @click="goToPage(incomplete.pagination.page - 1)">Sebelumnya</button>
                <button type="button" :disabled="incomplete.pagination.page >= incomplete.pagination.last_page" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm disabled:opacity-40" @click="goToPage(incomplete.pagination.page + 1)">Berikutnya</button>
            </div>
        </div>

        <!-- Sudah Dicatat -->
        <div v-else class="mt-4">
            <p v-if="!recorded.items.length" class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                Belum ada outcome yang dicatat.
            </p>
            <div v-else class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr><th class="px-4 py-3">Lamaran</th><th class="px-4 py-3">Outcome</th><th class="px-4 py-3">Dilaporkan oleh</th><th class="px-4 py-3">Dicatat</th><th class="px-4 py-3" /></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template v-for="row in recorded.items" :key="row.id">
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ row.vacancy_title ?? `Lowongan #${row.application_id}` }}</p>
                                    <p class="text-xs text-slate-500">Kode {{ row.application_code ?? row.application_id }}</p>
                                </td>
                                <td class="px-4 py-3">{{ statusLabel(applicationStatusLabel, row.outcome) }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ row.reported_by_source }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ formatDateTime(row.created_at) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Link v-if="row.application_id" :href="`/pelamar/${row.application_id}`" class="text-xs font-semibold text-[#0061a5] hover:underline">Pelamar</Link>
                                    <button v-if="can_write && editFor !== row.id" type="button" class="ml-3 text-xs font-semibold text-[#0061a5] hover:underline" @click="openEdit(row)">Koreksi</button>
                                </td>
                            </tr>
                            <tr v-if="row.notes && editFor !== row.id"><td colspan="5" class="px-4 pb-3 text-xs text-slate-600">Catatan: {{ row.notes }}</td></tr>
                            <tr v-if="editFor === row.id">
                                <td colspan="5" class="px-4 py-3">
                                    <form class="space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-4" @submit.prevent="submitEdit(row.id)">
                                        <p class="text-xs text-slate-500">Sumber lamaran ({{ row.source_type }}) dan aplikasi tidak dapat diubah.</p>
                                        <label class="block text-xs font-medium text-slate-700">Outcome
                                            <select v-model="editForm.outcome" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                                                <option v-for="code in internalApplicationOutcomeOptions" :key="code" :value="code">{{ statusLabel(applicationStatusLabel, code) }}</option>
                                            </select>
                                        </label>
                                        <label class="block text-xs font-medium text-slate-700">Dilaporkan oleh
                                            <select v-model="editForm.reported_by_source" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                                                <option v-for="src in reportedBySourceOptions" :key="src" :value="src">{{ src }}</option>
                                            </select>
                                        </label>
                                        <label class="block text-xs font-medium text-slate-700">Catatan<textarea v-model="editForm.notes" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" /></label>
                                        <div class="flex gap-3">
                                            <button type="submit" :disabled="submitting" class="rounded-lg bg-[#0061a5] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#004172] disabled:opacity-60">{{ submitting ? 'Menyimpan…' : 'Simpan Koreksi' }}</button>
                                            <button type="button" class="text-xs font-medium text-slate-600 hover:underline" @click="editFor = null">Batal</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div v-if="recorded.pagination.last_page > 1" class="mt-4 flex gap-2">
                <button type="button" :disabled="recorded.pagination.page <= 1" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm disabled:opacity-40" @click="goToPage(recorded.pagination.page - 1)">Sebelumnya</button>
                <button type="button" :disabled="recorded.pagination.page >= recorded.pagination.last_page" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm disabled:opacity-40" @click="goToPage(recorded.pagination.page + 1)">Berikutnya</button>
            </div>
        </div>
    </AppShell>
</template>
