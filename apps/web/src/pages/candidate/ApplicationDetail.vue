<script setup lang="ts">
/** Mirrors design/stitch/candidate/detail-lamaran's structure — not a pixel reproduction. */
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { authRequest, errorText, newIdempotencyKey } from '@/lib/auth'
import { applicationStatusBadgeClass, applicationStatusLabel, candidateHistoryLabel, formatDateTime, statusLabel } from '@/lib/labels'

interface HistoryEvent { event_type: string; from_status: string | null; to_status: string | null; stage_label: string | null; candidate_visible_note: string | null; occurred_at: string | null }
interface DocumentShare { id: number; snapshot_name: string; shared_at: string | null }
interface ScreeningAnswer { screening_question_id: number; answer_text: string | null; answer_boolean: boolean | null; answer_number: number | null; answer_option: string | null }

interface ApplicationDetail {
    id: number
    application_code: string
    vacancy_id: number
    vacancy_title: string | null
    current_status: string
    first_applied_at: string | null
    withdrawn_at: string | null
    reopen_count: number
    history?: HistoryEvent[]
    documents?: DocumentShare[]
    screening_answers?: ScreeningAnswer[]
}

const props = defineProps<{ application: ApplicationDetail }>()

const withdrawing = ref(false)
const withdrawReason = ref('')
const message = ref('')
const showWithdrawForm = ref(false)

const canWithdraw = computed(() => !['WITHDRAWN', 'HIRED', 'REJECTED', 'NO_SHOW'].includes(props.application.current_status))

async function withdraw() {
    withdrawing.value = true
    message.value = ''
    const { response, payload } = await authRequest(
        `/applications/${props.application.id}/withdraw`,
        { reason: withdrawReason.value || undefined },
        'POST',
        { 'Idempotency-Key': newIdempotencyKey() },
    )
    withdrawing.value = false
    if (!response.ok) { message.value = errorText(payload); return }
    router.reload({ only: ['application'] })
    showWithdrawForm.value = false
}
</script>

<template>
    <Head :title="`Lamaran ${application.application_code}`" />
    <AppShell persona="candidate" active="lamaran-saya" title="Detail Lamaran">
        <Link href="/lamaran-saya" class="text-sm font-medium text-[#0061a5] hover:underline">← Kembali ke Lamaran Saya</Link>

        <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-[#002045]">{{ application.vacancy_title ?? `Lowongan #${application.vacancy_id}` }}</h1>
                <p class="mt-2 text-sm text-slate-600">Kode lamaran {{ application.application_code }} · Diajukan {{ formatDateTime(application.first_applied_at) }}</p>
            </div>
            <span class="rounded-full px-3 py-1.5 text-sm font-semibold" :class="applicationStatusBadgeClass[application.current_status] ?? 'bg-slate-100 text-slate-700'">
                {{ statusLabel(applicationStatusLabel, application.current_status) }}
            </span>
        </div>

        <p v-if="message" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-[#93000a]" role="alert">{{ message }}</p>

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-[#002045]">Riwayat status</h2>
            <p v-if="!application.history?.length" class="mt-3 text-sm text-slate-500">Belum ada riwayat status yang dapat ditampilkan.</p>
            <ol v-else class="mt-4 space-y-4 border-l-2 border-slate-200 pl-4">
                <li v-for="(event, index) in application.history" :key="index">
                    <p class="text-sm font-semibold text-slate-800">{{ candidateHistoryLabel(event) }}</p>
                    <p class="text-xs text-slate-500">{{ formatDateTime(event.occurred_at) }}</p>
                    <p v-if="event.candidate_visible_note" class="mt-1 text-sm text-slate-600">{{ event.candidate_visible_note }}</p>
                </li>
            </ol>
        </section>

        <section v-if="application.documents?.length" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-[#002045]">Dokumen yang dibagikan</h2>
            <ul class="mt-3 space-y-2">
                <li v-for="document in application.documents" :key="document.id" class="text-sm text-slate-700">{{ document.snapshot_name }}</li>
            </ul>
        </section>

        <section v-if="canWithdraw" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-[#002045]">Pengunduran diri</h2>
            <p class="mt-2 text-sm text-slate-600">Anda dapat mengundurkan diri dari proses seleksi ini kapan saja sebelum keputusan akhir.</p>
            <button v-if="!showWithdrawForm" type="button" class="mt-4 rounded-lg border border-[#93000a] px-4 py-2 text-sm font-semibold text-[#93000a] hover:bg-red-50" @click="showWithdrawForm = true">
                Mengundurkan Diri
            </button>
            <form v-else class="mt-4 space-y-3" @submit.prevent="withdraw">
                <label class="block text-sm font-medium text-slate-700">
                    Alasan (opsional)
                    <textarea v-model="withdrawReason" rows="3" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" />
                </label>
                <div class="flex gap-3">
                    <button type="submit" :disabled="withdrawing" class="rounded-lg bg-[#93000a] px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 disabled:opacity-60">
                        {{ withdrawing ? 'Memproses…' : 'Konfirmasi Mengundurkan Diri' }}
                    </button>
                    <button type="button" class="text-sm font-medium text-slate-600 hover:underline" @click="showWithdrawForm = false">Batal</button>
                </div>
            </form>
        </section>
    </AppShell>
</template>
