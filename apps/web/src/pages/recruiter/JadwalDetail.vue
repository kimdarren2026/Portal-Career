<script setup lang="ts">
/** Recruiter schedule detail with reschedule/cancel. No `complete`/`no-show` controls — those backend actions remain deferred. */
import { Head, Link, router } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { authRequest, errorText, newIdempotencyKey } from '@/lib/auth'
import { formatDateTime, isoToZonedInput, scheduleEventLabel, scheduleMethodLabel, scheduleStatusLabel, statusLabel, zonedWallTimeToIso } from '@/lib/labels'

interface ScheduleDetail {
    id: number
    application_id: number
    recruitment_stage_id: number
    selection_type: string
    starts_at: string | null
    ends_at: string | null
    timezone: string
    method: string
    location: string | null
    meeting_url: string | null
    pic_user_id: number | null
    instructions: string | null
    status: string
    revision_number: number
}
interface HistoryEvent { event_type: string; actor_user_id: number | null; reason: string | null; occurred_at: string | null }

const props = defineProps<{ schedule: ScheduleDetail; history: HistoryEvent[]; application_id: number }>()

const canMutate = props.schedule.status === 'SCHEDULED' || props.schedule.status === 'RESCHEDULED'

const form = reactive({
    // Prefill the `datetime-local` inputs with the wall-clock time in the
    // schedule's own zone, not the raw UTC digits of the stored instant.
    starts_at: isoToZonedInput(props.schedule.starts_at, props.schedule.timezone),
    ends_at: isoToZonedInput(props.schedule.ends_at, props.schedule.timezone),
    timezone: props.schedule.timezone,
    method: props.schedule.method,
    location: props.schedule.location ?? '',
    meeting_url: props.schedule.meeting_url ?? '',
    instructions: props.schedule.instructions ?? '',
    reason: '',
})
const cancelReason = ref('')
const showReschedule = ref(false)
const showCancel = ref(false)
const submitting = ref(false)
const message = ref('')

async function reschedule() {
    submitting.value = true
    message.value = ''
    const payload: Record<string, unknown> = {
        // Convert wall-clock-in-`timezone` back to the absolute UTC instant the
        // frozen contract persists.
        starts_at: form.starts_at ? zonedWallTimeToIso(form.starts_at, form.timezone) : undefined,
        ends_at: form.ends_at ? zonedWallTimeToIso(form.ends_at, form.timezone) : null,
        timezone: form.timezone || undefined,
        method: form.method || undefined,
        location: form.method === 'ON_SITE' ? form.location : null,
        meeting_url: form.method === 'ONLINE' ? form.meeting_url : null,
        instructions: form.instructions || undefined,
        reason: form.reason || undefined,
    }
    const { response, payload: body } = await authRequest(`/schedules/${props.schedule.id}`, payload, 'PATCH', {
        'Idempotency-Key': newIdempotencyKey(),
        'If-Match': String(props.schedule.revision_number),
    })
    submitting.value = false
    if (!response.ok) { message.value = errorText(body); return }
    router.reload()
}

async function cancel() {
    submitting.value = true
    message.value = ''
    const { response, payload } = await authRequest(`/schedules/${props.schedule.id}/cancel`, { reason: cancelReason.value || undefined }, 'POST', {
        'Idempotency-Key': newIdempotencyKey(),
    })
    submitting.value = false
    if (!response.ok) { message.value = errorText(payload); return }
    router.reload()
}
</script>

<template>
    <Head title="Detail Jadwal Seleksi" />
    <AppShell persona="recruiter" active="jadwal-seleksi" title="Detail Jadwal">
        <Link :href="`/pelamar/${application_id}`" class="text-sm font-medium text-[#0061a5] hover:underline">← Kembali ke Detail Pelamar</Link>

        <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
            <h1 class="text-3xl font-bold tracking-tight text-[#002045]">{{ schedule.selection_type }}</h1>
            <span class="rounded-full bg-slate-100 px-3 py-1.5 text-sm font-semibold text-slate-700">{{ statusLabel(scheduleStatusLabel, schedule.status) }}</span>
        </div>

        <p v-if="message" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-[#93000a]" role="alert">{{ message }}</p>

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div><dt class="text-sm text-slate-500">Waktu Mulai</dt><dd class="mt-1 font-medium text-slate-800">{{ formatDateTime(schedule.starts_at, schedule.timezone) }}</dd></div>
                <div v-if="schedule.ends_at"><dt class="text-sm text-slate-500">Waktu Selesai</dt><dd class="mt-1 font-medium text-slate-800">{{ formatDateTime(schedule.ends_at, schedule.timezone) }}</dd></div>
                <div><dt class="text-sm text-slate-500">Metode</dt><dd class="mt-1 font-medium text-slate-800">{{ scheduleMethodLabel[schedule.method] ?? schedule.method }}</dd></div>
                <div v-if="schedule.method === 'ON_SITE' && schedule.location"><dt class="text-sm text-slate-500">Lokasi</dt><dd class="mt-1 font-medium text-slate-800">{{ schedule.location }}</dd></div>
                <div v-if="schedule.method === 'ONLINE' && schedule.meeting_url"><dt class="text-sm text-slate-500">Tautan Pertemuan</dt><dd class="mt-1 font-medium text-slate-800">{{ schedule.meeting_url }}</dd></div>
            </dl>
        </section>

        <section v-if="canMutate" class="mt-6 flex flex-wrap gap-3">
            <button type="button" class="rounded-lg bg-[#0061a5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#004172]" @click="showReschedule = !showReschedule">
                Jadwalkan Ulang
            </button>
            <button type="button" class="rounded-lg border border-[#93000a] px-4 py-2 text-sm font-semibold text-[#93000a] hover:bg-red-50" @click="showCancel = !showCancel">
                Batalkan Jadwal
            </button>
        </section>

        <form v-if="showReschedule" class="mt-4 space-y-4 rounded-2xl border border-slate-200 bg-white p-5" @submit.prevent="reschedule">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-medium text-slate-700">Waktu Mulai<input v-model="form.starts_at" type="datetime-local" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label>
                <label class="text-sm font-medium text-slate-700">Waktu Selesai (opsional)<input v-model="form.ends_at" type="datetime-local" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label>
                <label class="text-sm font-medium text-slate-700">Zona Waktu<input v-model="form.timezone" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" placeholder="Asia/Jakarta" /></label>
                <label class="text-sm font-medium text-slate-700">Metode
                    <select v-model="form.method" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]">
                        <option value="ONLINE">Daring (Online)</option>
                        <option value="ON_SITE">Tatap Muka (On-site)</option>
                    </select>
                </label>
                <label v-if="form.method === 'ON_SITE'" class="text-sm font-medium text-slate-700 sm:col-span-2">Lokasi<input v-model="form.location" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label>
                <label v-else class="text-sm font-medium text-slate-700 sm:col-span-2">Tautan Pertemuan<input v-model="form.meeting_url" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label>
                <label class="text-sm font-medium text-slate-700 sm:col-span-2">Instruksi<textarea v-model="form.instructions" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label>
                <label class="text-sm font-medium text-slate-700 sm:col-span-2">Alasan perubahan (opsional)<input v-model="form.reason" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label>
            </div>
            <button type="submit" :disabled="submitting" class="rounded-lg bg-[#0061a5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#004172] disabled:opacity-60">{{ submitting ? 'Menyimpan…' : 'Simpan Perubahan' }}</button>
        </form>

        <form v-if="showCancel" class="mt-4 space-y-3 rounded-2xl border border-slate-200 bg-white p-5" @submit.prevent="cancel">
            <label class="block text-sm font-medium text-slate-700">Alasan pembatalan (opsional)<textarea v-model="cancelReason" rows="2" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label>
            <button type="submit" :disabled="submitting" class="rounded-lg bg-[#93000a] px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 disabled:opacity-60">{{ submitting ? 'Memproses…' : 'Konfirmasi Pembatalan' }}</button>
        </form>

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-[#002045]">Riwayat</h2>
            <p v-if="!history.length" class="mt-3 text-sm text-slate-500">Belum ada riwayat perubahan.</p>
            <ol v-else class="mt-4 space-y-3 border-l-2 border-slate-200 pl-4">
                <li v-for="(event, index) in history" :key="index">
                    <p class="text-sm font-semibold text-slate-800">{{ scheduleEventLabel[event.event_type] ?? event.event_type }}</p>
                    <p class="text-xs text-slate-500">{{ formatDateTime(event.occurred_at) }}<span v-if="event.reason"> · {{ event.reason }}</span></p>
                </li>
            </ol>
        </section>
    </AppShell>
</template>
