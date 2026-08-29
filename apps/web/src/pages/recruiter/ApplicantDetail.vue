<script setup lang="ts">
/**
 * Mirrors design/stitch/kepegawaian/detail-pelamar and .../penilaian-kandidat
 * / .../offering structure (closest canonical references; no
 * company-recruiter-specific Stitch screen exists) — not a pixel
 * reproduction, and no HR/Kepegawaian business semantics are imported.
 * Exposes the frozen RA-1 transition edges, MS-3/MS-4 stage movement, the
 * Selection Schedule create action, and — Frontend Vertical Slice v2 — the
 * frozen Evaluation and Offering runtimes. Every mutation posts to its
 * existing JSON route; this page renders read-only embedded data.
 */
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { authRequest, errorText, newIdempotencyKey } from '@/lib/auth'
import { allowedTransitions, applicationHistoryLabel, applicationStatusBadgeClass, applicationStatusLabel, formatDateTime, scheduleMethodLabel, scheduleStatusLabel, statusLabel, zonedWallTimeToIso } from '@/lib/labels'

interface HistoryEvent { event_type: string; from_status: string | null; to_status: string | null; actor_user_id: number | null; reason: string | null; candidate_visibility: string; candidate_visible_note: string | null; occurred_at: string | null }
interface DocumentShare { id: number; snapshot_name: string; shared_at: string | null }
interface ScreeningAnswer { screening_question_id: number; answer_text: string | null; answer_boolean: boolean | null; answer_number: number | null; answer_option: string | null }
interface ApplicantDetail {
    id: number
    application_code: string
    vacancy_id: number
    vacancy_title: string | null
    current_status: string
    current_stage_id: number | null
    first_applied_at: string | null
    updated_at: string | null
    reopen_count: number
    current_version: number
    candidate: { candidate_profile_id?: number; name?: string; headline?: string }
    history: HistoryEvent[]
    documents: DocumentShare[]
    screening_answers: ScreeningAnswer[]
}
interface Stage { id: number; name: string; sort_order: number; candidate_visible_label: string | null }
interface ScheduleRow { id: number; selection_type: string; starts_at: string | null; timezone: string; method: string; status: string }
interface EvalItem { id: number; criterion: string; weight: number | null; score: number | null; comment: string | null; sort_order: number }
interface EvalRow {
    id: number; application_id: number; recruitment_stage_id: number; evaluator_user_id: number
    recommendation: string | null; comments: string | null; total_score: number | null
    submitted_at: string | null; created_at: string | null; updated_at: string | null; items: EvalItem[]
}
interface OfferRow {
    id: number; application_id: number; offered_by_user_id: number; status: string
    offered_at: string | null; response_deadline: string | null; note: string | null
    rejection_reason: string | null; sent_at: string | null; responded_at: string | null
    offer_accepted_at: string | null; created_at: string | null; updated_at: string | null
}

const props = defineProps<{ application: ApplicantDetail; stages: Stage[]; schedules: ScheduleRow[]; evaluations: EvalRow[]; offers: OfferRow[] }>()

const message = ref('')
const submitting = ref(false)

const TERMINAL = ['HIRED', 'REJECTED', 'WITHDRAWN', 'NO_SHOW']
const isTerminal = computed(() => TERMINAL.includes(props.application.current_status))
const currentUserId = computed(() => (usePage().props as any).auth?.user?.id ?? null)

const transitionOptions = computed(() => allowedTransitions[props.application.current_status] ?? [])
const transitionForm = reactive({ to_status: '', candidate_visibility: 'VISIBLE', candidate_visible_note: '', reason: '' })
const showTransition = ref(false)

async function submitTransition() {
    if (!transitionForm.to_status) return
    submitting.value = true
    message.value = ''
    const { response, payload } = await authRequest(
        `/applications/${props.application.id}/transition`,
        {
            to_status: transitionForm.to_status,
            candidate_visibility: transitionForm.candidate_visibility,
            candidate_visible_note: transitionForm.candidate_visible_note || undefined,
            reason: transitionForm.reason || undefined,
        },
        'POST',
        { 'Idempotency-Key': newIdempotencyKey(), 'If-Match': String(props.application.current_version) },
    )
    submitting.value = false
    if (!response.ok) { message.value = errorText(payload); return }
    showTransition.value = false
    router.reload()
}

const stageForm = reactive({ to_stage_id: '', candidate_visibility: 'INTERNAL', reason: '' })
const showStageMove = ref(false)

async function submitMoveStage() {
    if (!stageForm.to_stage_id) return
    submitting.value = true
    message.value = ''
    const { response, payload } = await authRequest(
        `/applications/${props.application.id}/move-stage`,
        { to_stage_id: Number(stageForm.to_stage_id), candidate_visibility: stageForm.candidate_visibility, reason: stageForm.reason || undefined },
        'POST',
        { 'Idempotency-Key': newIdempotencyKey() },
    )
    submitting.value = false
    if (!response.ok) { message.value = errorText(payload); return }
    showStageMove.value = false
    router.reload()
}

const scheduleForm = reactive({
    recruitment_stage_id: '', selection_type: '', starts_at: '', ends_at: '', timezone: 'Asia/Jakarta',
    method: 'ONLINE', location: '', meeting_url: '', instructions: '',
})
const showScheduleForm = ref(false)

async function submitSchedule() {
    submitting.value = true
    message.value = ''
    const { response, payload } = await authRequest(
        `/applications/${props.application.id}/schedules`,
        {
            recruitment_stage_id: Number(scheduleForm.recruitment_stage_id),
            selection_type: scheduleForm.selection_type,
            // `datetime-local` is wall-clock in `timezone`; the frozen contract
            // stores an absolute UTC instant, so convert before submitting.
            starts_at: zonedWallTimeToIso(scheduleForm.starts_at, scheduleForm.timezone),
            ends_at: scheduleForm.ends_at ? zonedWallTimeToIso(scheduleForm.ends_at, scheduleForm.timezone) : undefined,
            timezone: scheduleForm.timezone,
            method: scheduleForm.method,
            location: scheduleForm.method === 'ON_SITE' ? scheduleForm.location : undefined,
            meeting_url: scheduleForm.method === 'ONLINE' ? scheduleForm.meeting_url : undefined,
            instructions: scheduleForm.instructions || undefined,
        },
        'POST',
        { 'Idempotency-Key': newIdempotencyKey() },
    )
    submitting.value = false
    if (!response.ok) { message.value = errorText(payload); return }
    showScheduleForm.value = false
    router.reload()
}

/* ---- Evaluation (frozen EV-1 / EV-2 / RC-1) ---- */
const showEvalForm = ref(false)
const evalForm = reactive({
    recruitment_stage_id: '', recommendation: '', comments: '', total_score: '',
    items: [] as { criterion: string; weight: string; score: string; comment: string; sort_order: number }[],
})
function addEvalItem() {
    evalForm.items.push({ criterion: '', weight: '', score: '', comment: '', sort_order: evalForm.items.length })
}
function removeEvalItem(index: number) {
    evalForm.items.splice(index, 1)
    evalForm.items.forEach((item, i) => { item.sort_order = i })
}
async function submitCreateEvaluation() {
    if (!evalForm.recruitment_stage_id) return
    submitting.value = true
    message.value = ''
    const body: Record<string, unknown> = {
        recruitment_stage_id: Number(evalForm.recruitment_stage_id),
        recommendation: evalForm.recommendation || undefined,
        comments: evalForm.comments || undefined,
        total_score: evalForm.total_score === '' ? undefined : Number(evalForm.total_score),
    }
    if (evalForm.items.length) {
        body.items = evalForm.items.map((item) => ({
            criterion: item.criterion,
            weight: item.weight === '' ? undefined : Number(item.weight),
            score: item.score === '' ? undefined : Number(item.score),
            comment: item.comment || undefined,
            sort_order: item.sort_order,
        }))
    }
    const { response, payload } = await authRequest(`/applications/${props.application.id}/evaluations`, body, 'POST')
    submitting.value = false
    if (!response.ok) { message.value = errorText(payload); return }
    showEvalForm.value = false
    router.reload()
}

const evalEdit = reactive<Record<number, { recommendation: string; comments: string; total_score: string }>>({})
function closeEvalEdit(id: number) { delete evalEdit[id] }
function openEvalEdit(evaluation: EvalRow) {
    evalEdit[evaluation.id] = {
        recommendation: evaluation.recommendation ?? '',
        comments: evaluation.comments ?? '',
        total_score: evaluation.total_score === null || evaluation.total_score === undefined ? '' : String(evaluation.total_score),
    }
}
async function submitUpdateEvaluation(id: number) {
    submitting.value = true
    message.value = ''
    const edit = evalEdit[id]
    const { response, payload } = await authRequest(`/evaluations/${id}`, {
        recommendation: edit.recommendation || null,
        comments: edit.comments || null,
        total_score: edit.total_score === '' ? null : Number(edit.total_score),
    }, 'PATCH')
    submitting.value = false
    if (!response.ok) { message.value = errorText(payload); return }
    delete evalEdit[id]
    router.reload()
}
async function submitEvaluation(id: number) {
    submitting.value = true
    message.value = ''
    const { response, payload } = await authRequest(`/evaluations/${id}/submit`, {}, 'POST', { 'Idempotency-Key': newIdempotencyKey() })
    submitting.value = false
    if (!response.ok) { message.value = errorText(payload); return }
    router.reload()
}

/* ---- Offering (frozen OF-1 / OF-2) ---- */
const showOfferForm = ref(false)
const offerForm = reactive({ response_deadline: '', note: '', send_now: false })
async function submitCreateOffer() {
    submitting.value = true
    message.value = ''
    const { response, payload } = await authRequest(`/applications/${props.application.id}/offers`, {
        response_deadline: offerForm.response_deadline ? zonedWallTimeToIso(offerForm.response_deadline, 'Asia/Jakarta') : undefined,
        note: offerForm.note || undefined,
        send_now: offerForm.send_now || undefined,
    }, 'POST')
    submitting.value = false
    if (!response.ok) { message.value = errorText(payload); return }
    showOfferForm.value = false
    router.reload()
}
const offerEdit = reactive<Record<number, { response_deadline: string; note: string }>>({})
function closeOfferEdit(id: number) { delete offerEdit[id] }
function openOfferEdit(offer: OfferRow) {
    offerEdit[offer.id] = { response_deadline: '', note: offer.note ?? '' }
}
async function submitUpdateOffer(id: number) {
    submitting.value = true
    message.value = ''
    const edit = offerEdit[id]
    const { response, payload } = await authRequest(`/offers/${id}`, {
        response_deadline: edit.response_deadline ? zonedWallTimeToIso(edit.response_deadline, 'Asia/Jakarta') : undefined,
        note: edit.note || null,
    }, 'PATCH')
    submitting.value = false
    if (!response.ok) { message.value = errorText(payload); return }
    delete offerEdit[id]
    router.reload()
}
async function sendOffer(id: number) {
    submitting.value = true
    message.value = ''
    const { response, payload } = await authRequest(`/offers/${id}/send`, {}, 'POST', { 'Idempotency-Key': newIdempotencyKey() })
    submitting.value = false
    if (!response.ok) { message.value = errorText(payload); return }
    router.reload()
}
</script>

<template>
    <Head :title="`Pelamar ${application.application_code}`" />
    <AppShell persona="recruiter" active="pelamar" title="Detail Pelamar">
        <Link href="/pelamar" class="text-sm font-medium text-[#0061a5] hover:underline">← Kembali ke Pelamar</Link>

        <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-[#002045]">{{ application.candidate.name ?? 'Kandidat' }}</h1>
                <p class="mt-1 text-slate-600">{{ application.candidate.headline ?? '' }}</p>
                <p class="mt-2 text-sm text-slate-500">{{ application.vacancy_title ?? `Lowongan #${application.vacancy_id}` }} · Kode {{ application.application_code }} · Diajukan {{ formatDateTime(application.first_applied_at) }}</p>
            </div>
            <span class="rounded-full px-3 py-1.5 text-sm font-semibold" :class="applicationStatusBadgeClass[application.current_status] ?? 'bg-slate-100 text-slate-700'">
                {{ statusLabel(applicationStatusLabel, application.current_status) }}
            </span>
        </div>

        <p v-if="message" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-[#93000a]" role="alert">{{ message }}</p>

        <section class="mt-6 flex flex-wrap gap-3">
            <button v-if="transitionOptions.length" type="button" class="rounded-lg bg-[#0061a5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#004172]" @click="showTransition = !showTransition">
                Ubah Status
            </button>
            <button type="button" class="rounded-lg border border-[#0061a5] px-4 py-2 text-sm font-semibold text-[#0061a5] hover:bg-sky-50" @click="showStageMove = !showStageMove">
                Pindahkan Tahap
            </button>
            <button type="button" class="rounded-lg border border-[#0061a5] px-4 py-2 text-sm font-semibold text-[#0061a5] hover:bg-sky-50" @click="showScheduleForm = !showScheduleForm">
                Buat Jadwal Seleksi
            </button>
            <button v-if="!isTerminal" type="button" class="rounded-lg border border-[#0061a5] px-4 py-2 text-sm font-semibold text-[#0061a5] hover:bg-sky-50" @click="showEvalForm = !showEvalForm">
                Buat Penilaian
            </button>
            <button v-if="!isTerminal" type="button" class="rounded-lg border border-[#0061a5] px-4 py-2 text-sm font-semibold text-[#0061a5] hover:bg-sky-50" @click="showOfferForm = !showOfferForm">
                Buat Penawaran
            </button>
        </section>
        <p v-if="isTerminal" class="mt-2 text-xs text-slate-500">Lamaran berada pada status akhir — pembuatan penilaian dan penawaran baru tidak tersedia. Pencatatan outcome tetap dilakukan di Outcome Rekrutmen.</p>

        <form v-if="showTransition" class="mt-4 space-y-4 rounded-2xl border border-slate-200 bg-white p-5" @submit.prevent="submitTransition">
            <label class="block text-sm font-medium text-slate-700">
                Status Baru
                <select v-model="transitionForm.to_status" required class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]">
                    <option value="" disabled>Pilih status</option>
                    <option v-for="status in transitionOptions" :key="status" :value="status">{{ statusLabel(applicationStatusLabel, status) }}</option>
                </select>
            </label>
            <label class="block text-sm font-medium text-slate-700">
                Visibilitas untuk kandidat
                <select v-model="transitionForm.candidate_visibility" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]">
                    <option value="VISIBLE">Terlihat oleh kandidat</option>
                    <option value="INTERNAL">Internal saja</option>
                </select>
            </label>
            <label v-if="transitionForm.candidate_visibility === 'VISIBLE'" class="block text-sm font-medium text-slate-700">
                Catatan untuk kandidat (opsional)
                <textarea v-model="transitionForm.candidate_visible_note" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" />
            </label>
            <label class="block text-sm font-medium text-slate-700">
                Catatan internal (opsional)
                <textarea v-model="transitionForm.reason" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" />
            </label>
            <button type="submit" :disabled="submitting" class="rounded-lg bg-[#0061a5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#004172] disabled:opacity-60">{{ submitting ? 'Menyimpan…' : 'Simpan Status' }}</button>
        </form>

        <form v-if="showStageMove" class="mt-4 space-y-4 rounded-2xl border border-slate-200 bg-white p-5" @submit.prevent="submitMoveStage">
            <label class="block text-sm font-medium text-slate-700">
                Tahap Tujuan
                <select v-model="stageForm.to_stage_id" required class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]">
                    <option value="" disabled>Pilih tahap</option>
                    <option v-for="stage in stages" :key="stage.id" :value="stage.id">{{ stage.name }}</option>
                </select>
            </label>
            <label class="block text-sm font-medium text-slate-700">
                Visibilitas untuk kandidat
                <select v-model="stageForm.candidate_visibility" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]">
                    <option value="INTERNAL">Internal saja</option>
                    <option value="VISIBLE">Terlihat oleh kandidat</option>
                </select>
            </label>
            <label class="block text-sm font-medium text-slate-700">
                Alasan (opsional)
                <input v-model="stageForm.reason" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" />
            </label>
            <button type="submit" :disabled="submitting" class="rounded-lg bg-[#0061a5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#004172] disabled:opacity-60">{{ submitting ? 'Menyimpan…' : 'Pindahkan' }}</button>
        </form>

        <form v-if="showScheduleForm" class="mt-4 space-y-4 rounded-2xl border border-slate-200 bg-white p-5" @submit.prevent="submitSchedule">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-medium text-slate-700">Tahap<select v-model="scheduleForm.recruitment_stage_id" required class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]"><option value="" disabled>Pilih tahap</option><option v-for="stage in stages" :key="stage.id" :value="stage.id">{{ stage.name }}</option></select></label>
                <label class="text-sm font-medium text-slate-700">Jenis Seleksi<input v-model="scheduleForm.selection_type" required class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" placeholder="Contoh: Wawancara HR" /></label>
                <label class="text-sm font-medium text-slate-700">Waktu Mulai<input v-model="scheduleForm.starts_at" type="datetime-local" required class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label>
                <label class="text-sm font-medium text-slate-700">Waktu Selesai (opsional)<input v-model="scheduleForm.ends_at" type="datetime-local" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label>
                <label class="text-sm font-medium text-slate-700">Zona Waktu<input v-model="scheduleForm.timezone" required class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label>
                <label class="text-sm font-medium text-slate-700">Metode
                    <select v-model="scheduleForm.method" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]">
                        <option value="ONLINE">Daring (Online)</option>
                        <option value="ON_SITE">Tatap Muka (On-site)</option>
                    </select>
                </label>
                <label v-if="scheduleForm.method === 'ON_SITE'" class="text-sm font-medium text-slate-700 sm:col-span-2">Lokasi<input v-model="scheduleForm.location" required class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label>
                <label v-else class="text-sm font-medium text-slate-700 sm:col-span-2">Tautan Pertemuan<input v-model="scheduleForm.meeting_url" required class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label>
                <label class="text-sm font-medium text-slate-700 sm:col-span-2">Instruksi (opsional)<textarea v-model="scheduleForm.instructions" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label>
            </div>
            <button type="submit" :disabled="submitting" class="rounded-lg bg-[#0061a5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#004172] disabled:opacity-60">{{ submitting ? 'Menyimpan…' : 'Buat Jadwal' }}</button>
        </form>

        <form v-if="showEvalForm" class="mt-4 space-y-4 rounded-2xl border border-slate-200 bg-white p-5" @submit.prevent="submitCreateEvaluation">
            <h3 class="text-sm font-semibold text-[#002045]">Penilaian Baru</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-medium text-slate-700">Tahap
                    <select v-model="evalForm.recruitment_stage_id" required class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]">
                        <option value="" disabled>Pilih tahap</option>
                        <option v-for="stage in stages" :key="stage.id" :value="stage.id">{{ stage.name }}</option>
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">Rekomendasi (opsional)<input v-model="evalForm.recommendation" maxlength="64" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label>
                <label class="text-sm font-medium text-slate-700">Total Skor (opsional)<input v-model="evalForm.total_score" type="number" step="any" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label>
                <label class="text-sm font-medium text-slate-700 sm:col-span-2">Catatan (opsional)<textarea v-model="evalForm.comments" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label>
            </div>
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-slate-700">Kriteria Penilaian (opsional)</span>
                    <button type="button" class="text-xs font-semibold text-[#0061a5] hover:underline" @click="addEvalItem">+ Tambah kriteria</button>
                </div>
                <div v-for="(item, index) in evalForm.items" :key="index" class="grid gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-12">
                    <input v-model="item.criterion" placeholder="Kriteria" required class="rounded-lg border-slate-300 text-sm sm:col-span-4" />
                    <input v-model="item.weight" type="number" step="any" placeholder="Bobot" class="rounded-lg border-slate-300 text-sm sm:col-span-2" />
                    <input v-model="item.score" type="number" step="any" placeholder="Skor" class="rounded-lg border-slate-300 text-sm sm:col-span-2" />
                    <input v-model="item.comment" placeholder="Komentar" class="rounded-lg border-slate-300 text-sm sm:col-span-3" />
                    <button type="button" class="text-xs font-semibold text-[#93000a] hover:underline sm:col-span-1" @click="removeEvalItem(index)">Hapus</button>
                </div>
            </div>
            <button type="submit" :disabled="submitting" class="rounded-lg bg-[#0061a5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#004172] disabled:opacity-60">{{ submitting ? 'Menyimpan…' : 'Simpan Draf Penilaian' }}</button>
        </form>

        <form v-if="showOfferForm" class="mt-4 space-y-4 rounded-2xl border border-slate-200 bg-white p-5" @submit.prevent="submitCreateOffer">
            <h3 class="text-sm font-semibold text-[#002045]">Penawaran Baru</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-medium text-slate-700">Batas Respons (opsional, waktu Asia/Jakarta)<input v-model="offerForm.response_deadline" type="datetime-local" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label>
                <label class="flex items-center gap-2 pt-6 text-sm font-medium text-slate-700"><input v-model="offerForm.send_now" type="checkbox" class="rounded border-slate-300 text-[#0061a5] focus:ring-[#0061a5]" /> Langsung kirim ke kandidat</label>
                <label class="text-sm font-medium text-slate-700 sm:col-span-2">Catatan / Pesan untuk kandidat (opsional)<textarea v-model="offerForm.note" rows="3" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label>
            </div>
            <button type="submit" :disabled="submitting" class="rounded-lg bg-[#0061a5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#004172] disabled:opacity-60">{{ submitting ? 'Menyimpan…' : (offerForm.send_now ? 'Buat & Kirim Penawaran' : 'Simpan Draf Penawaran') }}</button>
        </form>

        <section v-if="schedules.length" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-[#002045]">Jadwal Seleksi</h2>
            <ul class="mt-3 space-y-2">
                <li v-for="schedule in schedules" :key="schedule.id">
                    <Link :href="`/jadwal-seleksi/${schedule.id}`" class="flex items-center justify-between rounded-lg border border-slate-200 p-3 text-sm hover:border-[#0061a5]">
                        <span>{{ schedule.selection_type }} · {{ formatDateTime(schedule.starts_at, schedule.timezone) }} · {{ scheduleMethodLabel[schedule.method] ?? schedule.method }}</span>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ statusLabel(scheduleStatusLabel, schedule.status) }}</span>
                    </Link>
                </li>
            </ul>
        </section>

        <section v-if="evaluations.length" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-[#002045]">Penilaian</h2>
            <ul class="mt-3 space-y-3">
                <li v-for="evaluation in evaluations" :key="evaluation.id" class="rounded-lg border border-slate-200 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-slate-800">
                            {{ evaluation.recommendation || 'Tanpa rekomendasi' }}
                            <span v-if="evaluation.total_score !== null" class="ml-2 text-xs font-normal text-slate-500">Total skor: {{ evaluation.total_score }}</span>
                        </p>
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="evaluation.submitted_at ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'">
                            {{ evaluation.submitted_at ? 'Final' : 'Draf' }}
                        </span>
                    </div>
                    <p v-if="evaluation.comments" class="mt-1 text-sm text-slate-600">{{ evaluation.comments }}</p>
                    <ul v-if="evaluation.items.length" class="mt-2 space-y-1 text-xs text-slate-600">
                        <li v-for="item in evaluation.items" :key="item.id">
                            {{ item.criterion }}<span v-if="item.weight !== null"> · bobot {{ item.weight }}</span><span v-if="item.score !== null"> · skor {{ item.score }}</span><span v-if="item.comment"> — {{ item.comment }}</span>
                        </li>
                    </ul>
                    <p class="mt-2 text-xs text-slate-400">Dibuat {{ formatDateTime(evaluation.created_at) }}<span v-if="evaluation.submitted_at"> · Difinalisasi {{ formatDateTime(evaluation.submitted_at) }}</span></p>

                    <div v-if="!evaluation.submitted_at && evaluation.evaluator_user_id === currentUserId" class="mt-3">
                        <div v-if="!evalEdit[evaluation.id]" class="flex flex-wrap gap-3">
                            <button type="button" class="text-xs font-semibold text-[#0061a5] hover:underline" @click="openEvalEdit(evaluation)">Ubah</button>
                            <button type="button" :disabled="submitting" class="rounded-lg bg-[#0061a5] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#004172] disabled:opacity-60" @click="submitEvaluation(evaluation.id)">Kirim Penilaian</button>
                        </div>
                        <form v-else class="mt-2 space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-3" @submit.prevent="submitUpdateEvaluation(evaluation.id)">
                            <label class="block text-xs font-medium text-slate-700">Rekomendasi<input v-model="evalEdit[evaluation.id].recommendation" maxlength="64" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" /></label>
                            <label class="block text-xs font-medium text-slate-700">Total Skor<input v-model="evalEdit[evaluation.id].total_score" type="number" step="any" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" /></label>
                            <label class="block text-xs font-medium text-slate-700">Catatan<textarea v-model="evalEdit[evaluation.id].comments" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" /></label>
                            <div class="flex gap-3">
                                <button type="submit" :disabled="submitting" class="rounded-lg bg-[#0061a5] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#004172] disabled:opacity-60">Simpan</button>
                                <button type="button" class="text-xs font-medium text-slate-600 hover:underline" @click="closeEvalEdit(evaluation.id)">Batal</button>
                            </div>
                        </form>
                    </div>
                    <p v-else-if="!evaluation.submitted_at" class="mt-2 text-xs text-slate-400">Hanya evaluator pembuat yang dapat mengubah atau memfinalisasi penilaian ini.</p>
                </li>
            </ul>
        </section>

        <section v-if="offers.length" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-[#002045]">Penawaran</h2>
            <ul class="mt-3 space-y-3">
                <li v-for="offer in offers" :key="offer.id" class="rounded-lg border border-slate-200 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ offer.status }}</span>
                        <span class="text-xs text-slate-400">Dibuat {{ formatDateTime(offer.created_at) }}</span>
                    </div>
                    <dl class="mt-2 space-y-1 text-sm text-slate-600">
                        <div v-if="offer.sent_at"><dt class="inline text-slate-500">Dikirim:</dt> {{ formatDateTime(offer.sent_at) }}</div>
                        <div v-if="offer.response_deadline"><dt class="inline text-slate-500">Batas respons:</dt> {{ formatDateTime(offer.response_deadline) }}</div>
                        <div v-if="offer.responded_at"><dt class="inline text-slate-500">Direspons:</dt> {{ formatDateTime(offer.responded_at) }}</div>
                        <div v-if="offer.note"><dt class="inline text-slate-500">Catatan:</dt> {{ offer.note }}</div>
                        <div v-if="offer.rejection_reason"><dt class="inline text-slate-500">Alasan penolakan:</dt> {{ offer.rejection_reason }}</div>
                    </dl>

                    <div v-if="offer.status === 'DRAFT'" class="mt-3">
                        <div v-if="!offerEdit[offer.id]" class="flex flex-wrap gap-3">
                            <button type="button" class="text-xs font-semibold text-[#0061a5] hover:underline" @click="openOfferEdit(offer)">Ubah Draf</button>
                            <button type="button" :disabled="submitting" class="rounded-lg bg-[#0061a5] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#004172] disabled:opacity-60" @click="sendOffer(offer.id)">Kirim ke Kandidat</button>
                        </div>
                        <form v-else class="mt-2 space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-3" @submit.prevent="submitUpdateOffer(offer.id)">
                            <label class="block text-xs font-medium text-slate-700">Batas Respons (kosongkan untuk tidak mengubah)<input v-model="offerEdit[offer.id].response_deadline" type="datetime-local" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" /></label>
                            <label class="block text-xs font-medium text-slate-700">Catatan<textarea v-model="offerEdit[offer.id].note" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" /></label>
                            <div class="flex gap-3">
                                <button type="submit" :disabled="submitting" class="rounded-lg bg-[#0061a5] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#004172] disabled:opacity-60">Simpan</button>
                                <button type="button" class="text-xs font-medium text-slate-600 hover:underline" @click="closeOfferEdit(offer.id)">Batal</button>
                            </div>
                        </form>
                    </div>
                    <p v-if="offer.status === 'ACCEPTED'" class="mt-3 rounded-lg bg-green-50 p-3 text-xs text-green-800">
                        Penawaran diterima kandidat. Status lamaran kini {{ statusLabel(applicationStatusLabel, 'HIRED') }}. Catat hasil akhir di
                        <Link href="/outcome-rekrutmen" class="font-semibold underline">Outcome Rekrutmen</Link> — outcome tidak dibuat otomatis.
                    </p>
                </li>
            </ul>
        </section>

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-[#002045]">Riwayat</h2>
            <ol class="mt-4 space-y-3 border-l-2 border-slate-200 pl-4">
                <li v-for="(event, index) in application.history" :key="index">
                    <p class="text-sm font-semibold text-slate-800">{{ applicationHistoryLabel(event) }}</p>
                    <p class="text-xs text-slate-500">{{ formatDateTime(event.occurred_at) }} · {{ event.candidate_visibility === 'VISIBLE' ? 'Terlihat kandidat' : 'Internal' }}</p>
                    <p v-if="event.reason" class="mt-1 text-sm text-slate-600">{{ event.reason }}</p>
                </li>
            </ol>
        </section>

        <section v-if="application.screening_answers.length" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-[#002045]">Jawaban Pertanyaan Seleksi</h2>
            <ul class="mt-3 space-y-2 text-sm text-slate-700">
                <li v-for="(answer, index) in application.screening_answers" :key="index">
                    {{ answer.answer_text ?? answer.answer_option ?? answer.answer_number ?? (answer.answer_boolean === null ? '-' : (answer.answer_boolean ? 'Ya' : 'Tidak')) }}
                </li>
            </ul>
        </section>

        <section v-if="application.documents.length" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-[#002045]">Dokumen</h2>
            <ul class="mt-3 space-y-2 text-sm text-slate-700">
                <li v-for="document in application.documents" :key="document.id">{{ document.snapshot_name }}</li>
            </ul>
        </section>
    </AppShell>
</template>
