<script setup lang="ts">
/**
 * Mirrors design/stitch/candidate/lamar-lowongan-* flow (profile check →
 * screening → document selection → preview/send) collapsed into one page —
 * not a pixel reproduction. `SubmitApplication` (frozen) enforces every
 * business rule (eligibility, consent version, document ownership,
 * screening validity, one-application-per-candidate-per-vacancy); this form
 * only assembles a well-shaped request. The exact consent statement text is
 * still an open product-copy decision (`ApplicationConsentVersion`'s own
 * docblock: "the text itself... remain outside this milestone") — the
 * sentence below is a placeholder pending that decision, not a fabricated
 * business rule; the backend performs no semantic check on its hash.
 */
import { Head, Link } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { authRequest, errorText, newIdempotencyKey } from '@/lib/auth'

interface VacancyDetail {
    id: number
    slug: string
    title: string
    applies_externally: boolean
    company: { name: string }
}
interface ScreeningQuestion { id: number; question_text: string; question_type: string; required: boolean; options_definition: unknown }
interface DocumentOption { id: number; display_name: string; document_type: string }

const props = defineProps<{
    vacancy: VacancyDetail
    screening_questions: ScreeningQuestion[]
    existing_application_id: number | null
    documents: DocumentOption[]
}>()

const CONSENT_TEXT = 'Saya menyetujui data lamaran dan dokumen yang saya bagikan digunakan oleh perusahaan penerima untuk keperluan proses rekrutmen ini.'

const consentAccepted = ref(false)
const selectedDocumentIds = reactive<number[]>([])
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const answers = reactive<Record<number, any>>({})
const submitting = ref(false)
const message = ref('')
const errors = ref<Record<string, string[]>>({})

function toggleDocument(id: number) {
    const index = selectedDocumentIds.indexOf(id)
    if (index === -1) selectedDocumentIds.push(id)
    else selectedDocumentIds.splice(index, 1)
}

async function consentHash(): Promise<string> {
    if (typeof crypto !== 'undefined' && 'subtle' in crypto) {
        const bytes = new TextEncoder().encode(CONSENT_TEXT)
        const digest = await crypto.subtle.digest('SHA-256', bytes)
        const hex = Array.from(new Uint8Array(digest)).map((b) => b.toString(16).padStart(2, '0')).join('')
        return `sha256:${hex}`
    }
    return 'sha256:' + '0'.repeat(64)
}

const requiredQuestionsMissing = computed(() =>
    props.screening_questions.some((q) => q.required && (answers[q.id] === undefined || answers[q.id] === null || answers[q.id] === '')),
)

async function submit() {
    if (!consentAccepted.value || requiredQuestionsMissing.value) return
    submitting.value = true
    message.value = ''
    errors.value = {}

    const screeningAnswers = props.screening_questions
        .filter((q) => answers[q.id] !== undefined && answers[q.id] !== null && answers[q.id] !== '')
        .map((q) => ({ screening_question_id: q.id, answer_value: answers[q.id] }))

    const { response, payload } = await authRequest(
        `/vacancies/${props.vacancy.id}/applications`,
        {
            document_ids: [...selectedDocumentIds],
            screening_answers: screeningAnswers,
            consent: { consent_version: 'APPLICATION_CONSENT_2026_08', consent_text_hash_reference: await consentHash(), accepted: true },
        },
        'POST',
        { 'Idempotency-Key': newIdempotencyKey() },
    )
    submitting.value = false

    if (!response.ok) {
        errors.value = (payload as any).error?.details?.fields ?? {}
        message.value = errorText(payload)
        return
    }
    window.location.assign(`/lamaran-saya/${(payload as any).data.id}`)
}
</script>

<template>
    <Head :title="`Lamar — ${vacancy.title}`" />
    <AppShell persona="candidate" active="lowongan" title="Lamar Lowongan">
        <Link :href="`/lowongan/${vacancy.slug}`" class="text-sm font-medium text-[#0061a5] hover:underline">← Kembali ke detail lowongan</Link>

        <h1 class="mt-4 text-3xl font-bold tracking-tight text-[#002045]">Lamar: {{ vacancy.title }}</h1>
        <p class="mt-1 text-slate-600">{{ vacancy.company.name }}</p>

        <div v-if="vacancy.applies_externally" class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-6">
            <p class="font-semibold text-amber-900">Lamaran eksternal belum tersedia melalui portal</p>
            <p class="mt-2 text-sm text-amber-800">Lowongan ini menggunakan sistem penerimaan lamaran milik perusahaan (ATS eksternal). Alur konfirmasi lamaran eksternal belum diaktifkan pada portal ini.</p>
        </div>

        <div v-else-if="existing_application_id" class="mt-6 rounded-2xl border border-sky-200 bg-sky-50 p-6">
            <p class="font-semibold text-sky-900">Anda sudah melamar lowongan ini</p>
            <p class="mt-2 text-sm text-sky-800">Setiap kandidat hanya memiliki satu riwayat lamaran per lowongan.</p>
            <Link :href="`/lamaran-saya/${existing_application_id}`" class="mt-4 inline-block rounded-lg bg-[#0061a5] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#004172]">Lihat Lamaran Saya</Link>
        </div>

        <form v-else class="mt-6 space-y-6" @submit.prevent="submit">
            <p v-if="message" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-[#93000a]" role="alert">{{ message }}</p>

            <section v-if="screening_questions.length" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-[#002045]">Pertanyaan Seleksi</h2>
                <div class="mt-4 space-y-4">
                    <label v-for="question in screening_questions" :key="question.id" class="block text-sm font-medium text-slate-700">
                        {{ question.question_text }}<span v-if="question.required" class="text-[#93000a]"> *</span>
                        <textarea
                            v-if="question.question_type === 'TEXT'"
                            v-model="answers[question.id]" rows="2"
                            class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]"
                        />
                        <select
                            v-else-if="question.question_type === 'BOOLEAN'"
                            v-model="answers[question.id]"
                            class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]"
                        >
                            <option :value="null">Pilih jawaban</option>
                            <option :value="true">Ya</option>
                            <option :value="false">Tidak</option>
                        </select>
                        <input
                            v-else-if="question.question_type === 'NUMBER'"
                            v-model.number="answers[question.id]" type="number"
                            class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]"
                        />
                        <input
                            v-else
                            v-model="answers[question.id]" type="text"
                            class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]"
                        />
                        <span v-if="errors.screening_answers?.length" class="mt-1 block text-sm text-[#93000a]">{{ errors.screening_answers[0] }}</span>
                    </label>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-[#002045]">Pilih Dokumen</h2>
                <p v-if="!documents.length" class="mt-2 text-sm text-slate-500">Belum ada dokumen pribadi. Anda tetap dapat melamar tanpa melampirkan dokumen.</p>
                <ul v-else class="mt-3 space-y-2">
                    <li v-for="document in documents" :key="document.id" class="flex items-center gap-3 rounded-lg border border-slate-200 p-3">
                        <input :id="`doc-${document.id}`" type="checkbox" class="rounded border-slate-300 text-[#0061a5] focus:ring-[#0061a5]" :checked="selectedDocumentIds.includes(document.id)" @change="toggleDocument(document.id)" />
                        <label :for="`doc-${document.id}`" class="text-sm text-slate-700">{{ document.display_name }} <span class="text-slate-400">({{ document.document_type }})</span></label>
                    </li>
                </ul>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-[#002045]">Persetujuan Berbagi Data</h2>
                <label class="mt-3 flex items-start gap-3 text-sm text-slate-700">
                    <input v-model="consentAccepted" type="checkbox" required class="mt-0.5 rounded border-slate-300 text-[#0061a5] focus:ring-[#0061a5]" />
                    <span>{{ CONSENT_TEXT }}</span>
                </label>
            </section>

            <button
                type="submit"
                :disabled="submitting || !consentAccepted || requiredQuestionsMissing"
                class="rounded-lg bg-[#0061a5] px-6 py-3 text-sm font-semibold text-white hover:bg-[#004172] disabled:opacity-50"
            >
                {{ submitting ? 'Mengirim…' : 'Kirim Lamaran' }}
            </button>
        </form>
    </AppShell>
</template>
