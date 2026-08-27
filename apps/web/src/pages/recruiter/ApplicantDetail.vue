<script setup lang="ts">
/**
 * Mirrors design/stitch/kepegawaian/detail-pelamar's structure (closest
 * canonical reference; no company-recruiter-specific Stitch screen exists
 * for this concept) — not a pixel reproduction. Exposes only the frozen
 * RA-1 transition edges and MS-3/MS-4 stage movement; evaluation, offering,
 * and outcome actions are explicitly out of this slice.
 */
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { authRequest, errorText, newIdempotencyKey } from '@/lib/auth'
import { allowedTransitions, applicationStatusBadgeClass, applicationStatusLabel, formatDateTime, scheduleMethodLabel, scheduleStatusLabel, statusLabel } from '@/lib/labels'

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

const props = defineProps<{ application: ApplicantDetail; stages: Stage[]; schedules: ScheduleRow[] }>()

const message = ref('')
const submitting = ref(false)

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
            starts_at: scheduleForm.starts_at,
            ends_at: scheduleForm.ends_at || undefined,
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
        </section>

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

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-[#002045]">Riwayat</h2>
            <ol class="mt-4 space-y-3 border-l-2 border-slate-200 pl-4">
                <li v-for="(event, index) in application.history" :key="index">
                    <p class="text-sm font-semibold text-slate-800">{{ statusLabel(applicationStatusLabel, event.to_status) }}</p>
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
