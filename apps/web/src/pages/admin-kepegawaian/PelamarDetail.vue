<script setup lang="ts">
/** Admin Kepegawaian — campus applicant workflow (v8). Reuses the frozen
 *  JSON routes (transition / move-stage / schedules / evaluations / offers)
 *  which now carry the CAMPUS_SCOPE branch. Business semantics are Campus. */
import { Head, Link, router } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { authRequest, errorText, newIdempotencyKey } from '@/lib/auth'
import { allowedTransitions, applicationStatusBadgeClass, applicationStatusLabel, formatDateTime, scheduleStatusLabel, statusLabel } from '@/lib/labels'

interface App {
    id: number; application_code: string; vacancy_id: number; vacancy_title: string | null
    current_status: string; current_stage_id: number | null; first_applied_at: string | null
    current_version: number; candidate: { name?: string; headline?: string }
    history: { event_type: string; from_status: string | null; to_status: string | null; occurred_at: string | null }[]
}
interface Stage { id: number; name: string; candidate_visible_label: string | null }
interface Sched { id: number; selection_type: string; starts_at: string | null; timezone: string; method: string; status: string }
interface Eval { id: number; recruitment_stage_id: number; recommendation: string | null; total_score: number | null; submitted_at: string | null }
interface Offer { id: number; status: string; note: string | null; sent_at: string | null; responded_at: string | null }

const props = defineProps<{ application: App; stages: Stage[]; schedules: Sched[]; evaluations: Eval[]; offers: Offer[] }>()

const banner = ref('')
const nextStatuses = allowedTransitions[props.application.current_status] ?? []

function reload() { router.reload() }
function fail(payload: { error?: { message?: string } }) { banner.value = errorText(payload) }

/* transition */
const transitionForm = reactive({ to_status: nextStatuses[0] ?? '', candidate_visibility: 'INTERNAL', candidate_visible_note: '' })
const transitioning = ref(false)
async function transition() {
    transitioning.value = true
    banner.value = ''
    const { response, payload } = await authRequest(`/applications/${props.application.id}/transition`, {
        to_status: transitionForm.to_status,
        candidate_visibility: transitionForm.candidate_visibility,
        candidate_visible_note: transitionForm.candidate_visible_note || null,
    }, 'POST', { 'If-Match': String(props.application.current_version) })
    transitioning.value = false
    if (!response.ok) return fail(payload)
    reload()
}

/* move stage */
const stageTarget = ref<number | null>(props.application.current_stage_id)
const movingStage = ref(false)
async function moveStage() {
    if (!stageTarget.value) return
    movingStage.value = true
    banner.value = ''
    const { response, payload } = await authRequest(`/applications/${props.application.id}/move-stage`,
        { to_stage_id: stageTarget.value }, 'POST', { 'Idempotency-Key': newIdempotencyKey() })
    movingStage.value = false
    if (!response.ok) return fail(payload)
    reload()
}

/* schedule */
const scheduleForm = reactive({
    recruitment_stage_id: props.stages[0]?.id ?? null as number | null,
    selection_type: 'INTERVIEW', starts_at: '', timezone: 'Asia/Jakarta', method: 'ONLINE', meeting_url: '', location: '',
})
const scheduling = ref(false)
async function createSchedule() {
    scheduling.value = true
    banner.value = ''
    const { response, payload } = await authRequest(`/applications/${props.application.id}/schedules`, {
        recruitment_stage_id: scheduleForm.recruitment_stage_id,
        selection_type: scheduleForm.selection_type,
        starts_at: scheduleForm.starts_at ? new Date(scheduleForm.starts_at).toISOString() : null,
        timezone: scheduleForm.timezone,
        method: scheduleForm.method,
        meeting_url: scheduleForm.method === 'ONLINE' ? (scheduleForm.meeting_url || null) : null,
        location: scheduleForm.method === 'ON_SITE' ? (scheduleForm.location || null) : null,
    }, 'POST', { 'Idempotency-Key': newIdempotencyKey() })
    scheduling.value = false
    if (!response.ok) return fail(payload)
    reload()
}

/* evaluation */
const evalForm = reactive({ recruitment_stage_id: props.stages[0]?.id ?? null as number | null, recommendation: 'HIRE', comments: '', total_score: null as number | null })
const evaluating = ref(false)
async function createEvaluation() {
    evaluating.value = true
    banner.value = ''
    const { response, payload } = await authRequest(`/applications/${props.application.id}/evaluations`, {
        recruitment_stage_id: evalForm.recruitment_stage_id,
        recommendation: evalForm.recommendation,
        comments: evalForm.comments || null,
        total_score: evalForm.total_score,
    }, 'POST')
    evaluating.value = false
    if (!response.ok) return fail(payload)
    reload()
}
async function submitEvaluation(id: number) {
    banner.value = ''
    const { response, payload } = await authRequest(`/evaluations/${id}/submit`, {}, 'POST', { 'Idempotency-Key': newIdempotencyKey() })
    if (!response.ok) return fail(payload)
    reload()
}

/* offer */
const offerForm = reactive({ note: '', send_now: false })
const offering = ref(false)
async function createOffer() {
    offering.value = true
    banner.value = ''
    const { response, payload } = await authRequest(`/applications/${props.application.id}/offers`, {
        note: offerForm.note || null, send_now: offerForm.send_now,
    }, 'POST')
    offering.value = false
    if (!response.ok) return fail(payload)
    reload()
}
async function sendOffer(id: number) {
    banner.value = ''
    const { response, payload } = await authRequest(`/offers/${id}/send`, {}, 'POST', { 'Idempotency-Key': newIdempotencyKey() })
    if (!response.ok) return fail(payload)
    reload()
}

/* outcome */
const outcomeForm = reactive({ outcome: 'HIRED', notes: '' })
const recordingOutcome = ref(false)
async function recordOutcome() {
    recordingOutcome.value = true
    banner.value = ''
    const { response, payload } = await authRequest('/recruitment-outcomes', {
        source_type: 'INTERNAL_APPLICATION',
        application_id: props.application.id,
        outcome: outcomeForm.outcome,
        reported_by_source: 'CAMPUS_STAFF',
        notes: outcomeForm.notes || null,
    }, 'POST', { 'Idempotency-Key': newIdempotencyKey() })
    recordingOutcome.value = false
    if (!response.ok) return fail(payload)
    banner.value = ''
    router.visit('/kepegawaian/outcome-rekrutmen')
}

const terminal = ['HIRED', 'REJECTED', 'WITHDRAWN', 'NO_SHOW'].includes(props.application.current_status)
</script>

<template>
    <Head title="Detail Pelamar" />
    <AppShell persona="admin-kepegawaian" active="kepegawaian/pelamar" title="Detail Pelamar">
        <Link href="/kepegawaian/pelamar" class="text-sm font-semibold text-[#0061a5] hover:underline">← Kembali ke daftar pelamar</Link>

        <div class="mt-3 flex flex-wrap items-center gap-3">
            <h1 class="text-3xl font-bold tracking-tight text-[#002045]">{{ application.candidate.name ?? application.application_code }}</h1>
            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="applicationStatusBadgeClass[application.current_status] ?? 'bg-slate-100 text-slate-700'">
                {{ statusLabel(applicationStatusLabel, application.current_status) }}
            </span>
        </div>
        <p class="mt-1 text-sm text-slate-500">
            {{ application.application_code }} · {{ application.vacancy_title ?? `Lowongan #${application.vacancy_id}` }} · Diajukan {{ formatDateTime(application.first_applied_at) }}
        </p>

        <p v-if="banner" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-[#93000a]" role="alert">{{ banner }}</p>

        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <!-- status -->
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Ubah Status</h2>
                <div v-if="nextStatuses.length" class="mt-3 space-y-3">
                    <select v-model="transitionForm.to_status" class="block w-full rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]">
                        <option v-for="s in nextStatuses" :key="s" :value="s">{{ statusLabel(applicationStatusLabel, s) }}</option>
                    </select>
                    <select v-model="transitionForm.candidate_visibility" class="block w-full rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]">
                        <option value="INTERNAL">Internal (tidak terlihat kandidat)</option>
                        <option value="VISIBLE">Terlihat kandidat</option>
                    </select>
                    <input v-model="transitionForm.candidate_visible_note" placeholder="Catatan untuk kandidat (opsional)" class="block w-full rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]" />
                    <button type="button" :disabled="transitioning" class="rounded-lg bg-[#0061a5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#004172] disabled:opacity-60" @click="transition">Simpan Status</button>
                </div>
                <p v-else class="mt-3 text-sm text-slate-500">Tidak ada transisi status berikutnya dari status ini.</p>
            </section>

            <!-- stage -->
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Tahap Seleksi</h2>
                <div v-if="stages.length" class="mt-3 space-y-3">
                    <select v-model="stageTarget" class="block w-full rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]">
                        <option v-for="s in stages" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <button type="button" :disabled="movingStage" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-[#0061a5] disabled:opacity-60" @click="moveStage">Pindahkan Tahap</button>
                </div>
                <p v-else class="mt-3 text-sm text-slate-500">Belum ada tahap seleksi aktif untuk lowongan ini.</p>
            </section>
        </div>

        <!-- schedule -->
        <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Jadwal Seleksi</h2>
            <ul v-if="schedules.length" class="mt-3 space-y-1 text-sm text-slate-700">
                <li v-for="s in schedules" :key="s.id" class="flex flex-wrap gap-2">
                    <span class="font-medium">{{ s.selection_type }}</span>
                    <span>· {{ formatDateTime(s.starts_at, s.timezone) }}</span>
                    <span>· {{ statusLabel(scheduleStatusLabel, s.status) }}</span>
                </li>
            </ul>
            <form v-if="stages.length && !terminal" class="mt-4 grid gap-3 sm:grid-cols-2" @submit.prevent="createSchedule">
                <select v-model="scheduleForm.recruitment_stage_id" class="rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]">
                    <option v-for="s in stages" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <input v-model="scheduleForm.selection_type" placeholder="Jenis seleksi" class="rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]" />
                <input v-model="scheduleForm.starts_at" type="datetime-local" class="rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]" />
                <select v-model="scheduleForm.method" class="rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]">
                    <option value="ONLINE">Daring</option><option value="ON_SITE">Tatap Muka</option>
                </select>
                <input v-if="scheduleForm.method === 'ONLINE'" v-model="scheduleForm.meeting_url" placeholder="URL pertemuan" class="rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]" />
                <input v-else v-model="scheduleForm.location" placeholder="Lokasi" class="rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]" />
                <button type="submit" :disabled="scheduling" class="rounded-lg bg-[#0061a5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#004172] disabled:opacity-60 sm:col-span-2">Buat Jadwal</button>
            </form>
        </section>

        <!-- evaluation -->
        <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Penilaian</h2>
            <ul v-if="evaluations.length" class="mt-3 space-y-2 text-sm text-slate-700">
                <li v-for="e in evaluations" :key="e.id" class="flex flex-wrap items-center gap-2">
                    <span class="font-medium">{{ e.recommendation ?? '—' }}</span>
                    <span>· Skor {{ e.total_score ?? '—' }}</span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="e.submitted_at ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'">{{ e.submitted_at ? 'Terkirim' : 'Draf' }}</span>
                    <button v-if="!e.submitted_at" type="button" class="text-xs font-semibold text-[#0061a5] hover:underline" @click="submitEvaluation(e.id)">Kirim</button>
                </li>
            </ul>
            <form v-if="stages.length && !terminal" class="mt-4 grid gap-3 sm:grid-cols-2" @submit.prevent="createEvaluation">
                <select v-model="evalForm.recruitment_stage_id" class="rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]">
                    <option v-for="s in stages" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <select v-model="evalForm.recommendation" class="rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]">
                    <option value="HIRE">Rekomendasi Terima</option><option value="HOLD">Tahan</option><option value="REJECT">Tolak</option>
                </select>
                <input v-model.number="evalForm.total_score" type="number" step="0.1" placeholder="Total skor" class="rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]" />
                <input v-model="evalForm.comments" placeholder="Catatan" class="rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]" />
                <button type="submit" :disabled="evaluating" class="rounded-lg bg-[#0061a5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#004172] disabled:opacity-60 sm:col-span-2">Buat Penilaian</button>
            </form>
        </section>

        <!-- offer -->
        <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Offering</h2>
            <ul v-if="offers.length" class="mt-3 space-y-2 text-sm text-slate-700">
                <li v-for="o in offers" :key="o.id" class="flex flex-wrap items-center gap-2">
                    <span class="font-medium">{{ o.status }}</span>
                    <span v-if="o.sent_at">· dikirim {{ formatDateTime(o.sent_at) }}</span>
                    <button v-if="o.status === 'DRAFT'" type="button" class="text-xs font-semibold text-[#0061a5] hover:underline" @click="sendOffer(o.id)">Kirim ke kandidat</button>
                </li>
            </ul>
            <form v-if="!terminal" class="mt-4 space-y-3" @submit.prevent="createOffer">
                <textarea v-model="offerForm.note" rows="2" placeholder="Catatan penawaran" class="block w-full rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]" />
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="offerForm.send_now" type="checkbox" class="rounded border-slate-300 text-[#0061a5] focus:ring-[#0061a5]" /> Kirim langsung setelah dibuat
                </label>
                <button type="submit" :disabled="offering" class="rounded-lg bg-[#0061a5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#004172] disabled:opacity-60">Buat Penawaran</button>
            </form>
        </section>

        <!-- outcome -->
        <section v-if="terminal" class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Outcome Rekrutmen</h2>
            <p class="mt-1 text-sm text-slate-600">Aplikasi berstatus terminal — catat outcome secara eksplisit (tidak otomatis).</p>
            <form class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="recordOutcome">
                <select v-model="outcomeForm.outcome" class="rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]">
                    <option value="HIRED">Diterima Bekerja</option><option value="REJECTED">Tidak Lolos</option>
                    <option value="WITHDRAWN">Mengundurkan Diri</option><option value="NO_SHOW">Tidak Hadir</option>
                </select>
                <input v-model="outcomeForm.notes" placeholder="Catatan (opsional)" class="rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]" />
                <button type="submit" :disabled="recordingOutcome" class="rounded-lg bg-[#0061a5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#004172] disabled:opacity-60 sm:col-span-2">Catat Outcome</button>
            </form>
        </section>

        <!-- history -->
        <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Riwayat</h2>
            <ol class="mt-3 space-y-1 text-sm text-slate-600">
                <li v-for="(h, i) in application.history" :key="i">
                    {{ formatDateTime(h.occurred_at) }} — {{ h.event_type }}<span v-if="h.to_status"> → {{ h.to_status }}</span>
                </li>
            </ol>
        </section>
    </AppShell>
</template>
