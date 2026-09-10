<script setup lang="ts">
/**
 * Laporkan Lowongan — public anti-fraud report form (PGC-V1 / PD-C).
 * Works for anonymous and authenticated reporters. `details` is required
 * only when reason = OTHER; name/email are optional for anonymous reporters.
 */
import { Head, Link } from '@inertiajs/vue3'
import { reactive, ref, computed } from 'vue'

interface ReasonOption { code: string; label: string }
const props = defineProps<{
    vacancy: { slug: string; title: string; company: string | null }
    reasons: ReasonOption[]
    authenticated: boolean
}>()

const form = reactive({ reason: '', details: '', reporter_name: '', reporter_email: '' })
const busy = ref(false)
const done = ref(false)
const fieldErrors = ref<Record<string, string[]>>({})
const generalError = ref('')

const detailsRequired = computed(() => form.reason === 'OTHER')

async function submit() {
    busy.value = true
    fieldErrors.value = {}
    generalError.value = ''
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
    const body: Record<string, unknown> = { reason: form.reason, details: form.details || null }
    if (!props.authenticated) {
        body.reporter_name = form.reporter_name || null
        body.reporter_email = form.reporter_email || null
    }
    const response = await fetch(`/lowongan/${props.vacancy.slug}/laporkan`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify(body),
    })
    const payload = (await response.json()) as {
        data?: unknown
        error?: { message?: string; details?: { fields?: Record<string, string[]> } }
    }
    busy.value = false
    if (response.ok) {
        done.value = true
        return
    }
    fieldErrors.value = payload.error?.details?.fields ?? {}
    generalError.value = payload.error?.message ?? 'Gagal mengirim laporan.'
}
</script>

<template>
    <Head title="Laporkan Lowongan" />

    <div class="min-h-screen bg-[#f6f8fc] px-5 py-12 text-[#181c1e]">
        <div class="mx-auto max-w-xl">
            <Link :href="`/lowongan/${vacancy.slug}`" class="text-sm font-medium text-[#0061a5] hover:underline">&larr; Kembali ke lowongan</Link>

            <h1 class="mt-4 text-2xl font-bold text-[#002045]">Laporkan Lowongan</h1>
            <p class="mt-1 text-sm text-slate-600">
                <span class="font-medium">{{ vacancy.title }}</span>
                <span v-if="vacancy.company"> &middot; {{ vacancy.company }}</span>
            </p>

            <div v-if="done" class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-6 text-sm text-emerald-800">
                Terima kasih. Laporan Anda telah diterima dan akan ditinjau oleh Career Center.
            </div>

            <form v-else class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-6" @submit.prevent="submit">
                <label class="block text-sm font-medium text-slate-700">
                    Alasan
                    <select v-model="form.reason" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                        <option value="" disabled>Pilih alasan</option>
                        <option v-for="r in reasons" :key="r.code" :value="r.code">{{ r.label }}</option>
                    </select>
                    <span v-if="fieldErrors.reason" class="mt-1 block text-xs text-[#93000a]">{{ fieldErrors.reason[0] }}</span>
                </label>

                <label class="block text-sm font-medium text-slate-700">
                    Detail <span v-if="detailsRequired" class="text-[#93000a]">(wajib)</span><span v-else class="text-slate-400">(opsional)</span>
                    <textarea v-model="form.details" rows="4" maxlength="2000" :required="detailsRequired" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" placeholder="Jelaskan apa yang mencurigakan…"></textarea>
                    <span v-if="fieldErrors.details" class="mt-1 block text-xs text-[#93000a]">{{ fieldErrors.details[0] }}</span>
                </label>

                <template v-if="!authenticated">
                    <label class="block text-sm font-medium text-slate-700">
                        Nama <span class="text-slate-400">(opsional)</span>
                        <input v-model="form.reporter_name" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                    </label>
                    <label class="block text-sm font-medium text-slate-700">
                        Email <span class="text-slate-400">(opsional)</span>
                        <input v-model="form.reporter_email" type="email" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                        <span v-if="fieldErrors.reporter_email" class="mt-1 block text-xs text-[#93000a]">{{ fieldErrors.reporter_email[0] }}</span>
                    </label>
                </template>

                <p v-if="generalError" class="text-sm text-[#93000a]" role="alert">{{ generalError }}</p>

                <button type="submit" :disabled="busy || !form.reason" class="w-full rounded-lg bg-[#002045] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#1a365d] disabled:opacity-50">
                    {{ busy ? 'Mengirim…' : 'Kirim laporan' }}
                </button>
            </form>
        </div>
    </div>
</template>
