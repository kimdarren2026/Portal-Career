<script setup lang="ts">
/**
 * Super Admin "Konfigurasi SMTP" (SMTP Configuration Foundation + Frontend
 * Slice v11) — the browser realization of GET/PUT /admin/smtp-configuration
 * and POST /admin/smtp-configuration/test (FR-NOTIF-005, ADR-015).
 *
 * SECRET HANDLING (INV-035): the credential is write-only. The server never
 * sends it (only `secret_configured`), so it is never in these props, never
 * in the DOM, and never rendered. The password field starts empty, is never
 * pre-filled, uses `autocomplete="new-password"`, and is wiped from component
 * state immediately after a successful save. Leaving it blank preserves the
 * stored credential; the frozen PUT contract defines exactly that.
 */
import { Head, router } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { authRequest, errorText, newIdempotencyKey } from '@/lib/auth'
import { formatDateTime } from '@/lib/labels'

interface SmtpConfig {
    host: string | null
    port: number | null
    encryption_mode: string | null
    username: string | null
    from_address: string | null
    from_name: string | null
    reply_to_address: string | null
    timeout_seconds: number | null
    max_attempts: number | null
    retry_backoff_seconds: number | null
    is_active: boolean
    last_tested_at: string | null
    last_test_result: string | null
    updated_by_user_id: number | null
    updated_at: string | null
    secret_configured: boolean
}

const props = defineProps<{ configuration: SmtpConfig | null }>()

const c = props.configuration
const form = reactive({
    host: c?.host ?? '',
    port: c?.port ?? 587,
    encryption_mode: c?.encryption_mode ?? 'STARTTLS',
    username: c?.username ?? '',
    password: '',
    from_address: c?.from_address ?? '',
    from_name: c?.from_name ?? '',
    reply_to_address: c?.reply_to_address ?? '',
    timeout_seconds: c?.timeout_seconds ?? null,
    max_attempts: c?.max_attempts ?? 3,
    retry_backoff_seconds: c?.retry_backoff_seconds ?? 60,
    is_active: c?.is_active ?? true,
})
// True until the user types into the password field; controls whether we send
// the `password` key at all (omitted = preserve existing secret).
const passwordUntouched = ref(true)

const saving = ref(false)
const saveOk = ref(false)
const saveError = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const testRecipient = ref('')
const testing = ref(false)
const testResult = ref<'SUCCESS' | 'FAILURE' | ''>('')
const testMessage = ref('')

async function save() {
    saving.value = true
    saveOk.value = false
    saveError.value = ''
    fieldErrors.value = {}

    const body: Record<string, unknown> = {
        host: form.host,
        port: Number(form.port),
        encryption_mode: form.encryption_mode,
        username: form.username || null,
        from_address: form.from_address,
        from_name: form.from_name || null,
        reply_to_address: form.reply_to_address || null,
        timeout_seconds: form.timeout_seconds === null || form.timeout_seconds === ('' as unknown) ? null : Number(form.timeout_seconds),
        max_attempts: Number(form.max_attempts),
        retry_backoff_seconds: Number(form.retry_backoff_seconds),
        is_active: form.is_active,
    }
    // Only send `password` when the admin actually entered one. Omitting it
    // preserves the stored credential (frozen PUT semantics).
    if (!passwordUntouched.value) body.password = form.password

    const { response, payload } = await authRequest('/admin/smtp-configuration', body, 'PUT', {
        'Idempotency-Key': newIdempotencyKey(),
    })
    saving.value = false

    if (!response.ok) {
        saveError.value = errorText(payload)
        fieldErrors.value = payload.error?.details?.fields ?? {}
        return
    }

    // Never keep the secret in memory after a successful save.
    form.password = ''
    passwordUntouched.value = true
    saveOk.value = true
    router.reload({ only: ['configuration'] })
}

async function sendTest() {
    testing.value = true
    testResult.value = ''
    testMessage.value = ''

    const { response, payload } = await authRequest('/admin/smtp-configuration/test', { recipient: testRecipient.value }, 'POST')
    testing.value = false

    if (!response.ok) {
        testResult.value = 'FAILURE'
        testMessage.value = errorText(payload)
        return
    }
    const data = (payload.data ?? {}) as { result?: string; message?: string }
    testResult.value = data.result === 'SUCCESS' ? 'SUCCESS' : 'FAILURE'
    testMessage.value = data.message ?? (testResult.value === 'SUCCESS' ? 'Email uji berhasil dikirim.' : 'Pengiriman uji gagal.')
    router.reload({ only: ['configuration'] })
}

function fieldError(name: string): string | null {
    return fieldErrors.value[name]?.[0] ?? null
}
</script>

<template>
    <Head title="Konfigurasi SMTP" />
    <AppShell persona="super-admin" active="konfigurasi-smtp" title="Konfigurasi SMTP">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Konfigurasi SMTP</h1>
        <p class="mt-2 max-w-2xl text-slate-600">
            Pengaturan pengiriman email transaksional. Kredensial bersifat
            <span class="font-semibold">write-only</span> — tidak pernah ditampilkan kembali setelah disimpan.
        </p>

        <dl v-if="configuration" class="mt-4 flex flex-wrap gap-x-8 gap-y-1 text-xs text-slate-500">
            <div><dt class="inline font-medium">Status:</dt> <dd class="inline">{{ configuration.is_active ? 'Aktif' : 'Tidak aktif' }}</dd></div>
            <div><dt class="inline font-medium">Kredensial:</dt> <dd class="inline">{{ configuration.secret_configured ? 'Tersimpan' : 'Belum diatur' }}</dd></div>
            <div><dt class="inline font-medium">Uji terakhir:</dt> <dd class="inline">{{ configuration.last_test_result ?? 'NOT_TESTED' }}<span v-if="configuration.last_tested_at"> · {{ formatDateTime(configuration.last_tested_at) }}</span></dd></div>
            <div><dt class="inline font-medium">Diperbarui:</dt> <dd class="inline">{{ formatDateTime(configuration.updated_at) }}</dd></div>
        </dl>
        <p v-else class="mt-4 text-xs text-slate-500">Belum ada konfigurasi runtime — pengiriman memakai konfigurasi deployment.</p>

        <form class="mt-6 grid max-w-3xl gap-4 sm:grid-cols-2" @submit.prevent="save">
            <label class="text-sm font-medium text-slate-700">Host
                <input v-model="form.host" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                <span v-if="fieldError('host')" class="mt-1 block text-xs text-[#93000a]">{{ fieldError('host') }}</span>
            </label>
            <label class="text-sm font-medium text-slate-700">Port
                <input v-model.number="form.port" type="number" min="1" max="65535" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                <span v-if="fieldError('port')" class="mt-1 block text-xs text-[#93000a]">{{ fieldError('port') }}</span>
            </label>
            <label class="text-sm font-medium text-slate-700">Mode enkripsi
                <select v-model="form.encryption_mode" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                    <option value="NONE">NONE</option>
                    <option value="STARTTLS">STARTTLS</option>
                    <option value="TLS">TLS</option>
                </select>
            </label>
            <label class="text-sm font-medium text-slate-700">Username
                <input v-model="form.username" autocomplete="off" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
            </label>
            <label class="text-sm font-medium text-slate-700 sm:col-span-2">Kredensial / Password
                <input
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    :placeholder="configuration?.secret_configured ? 'Kosongkan untuk mempertahankan kredensial tersimpan' : 'Masukkan kredensial SMTP'"
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm"
                    @input="passwordUntouched = false"
                />
                <span class="mt-1 block text-xs text-slate-400">
                    {{ configuration?.secret_configured
                        ? 'Kredensial sudah tersimpan. Biarkan kosong untuk mempertahankannya; isi untuk menggantinya.'
                        : 'Belum ada kredensial tersimpan.' }}
                </span>
                <span v-if="fieldError('password')" class="mt-1 block text-xs text-[#93000a]">{{ fieldError('password') }}</span>
            </label>
            <label class="text-sm font-medium text-slate-700">From address
                <input v-model="form.from_address" type="email" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                <span v-if="fieldError('from_address')" class="mt-1 block text-xs text-[#93000a]">{{ fieldError('from_address') }}</span>
            </label>
            <label class="text-sm font-medium text-slate-700">From name
                <input v-model="form.from_name" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
            </label>
            <label class="text-sm font-medium text-slate-700">Reply-to address
                <input v-model="form.reply_to_address" type="email" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                <span v-if="fieldError('reply_to_address')" class="mt-1 block text-xs text-[#93000a]">{{ fieldError('reply_to_address') }}</span>
            </label>
            <label class="text-sm font-medium text-slate-700">Timeout (detik)
                <input v-model.number="form.timeout_seconds" type="number" min="1" max="600" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
            </label>
            <label class="text-sm font-medium text-slate-700">Max attempts
                <input v-model.number="form.max_attempts" type="number" min="1" max="20" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                <span v-if="fieldError('max_attempts')" class="mt-1 block text-xs text-[#93000a]">{{ fieldError('max_attempts') }}</span>
            </label>
            <label class="text-sm font-medium text-slate-700">Retry backoff (detik)
                <input v-model.number="form.retry_backoff_seconds" type="number" min="1" max="86400" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm" />
                <span v-if="fieldError('retry_backoff_seconds')" class="mt-1 block text-xs text-[#93000a]">{{ fieldError('retry_backoff_seconds') }}</span>
            </label>
            <label class="flex items-center gap-2 text-sm font-medium text-slate-700 sm:col-span-2">
                <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-[#0061a5]" />
                Jadikan konfigurasi aktif
            </label>

            <div class="sm:col-span-2">
                <button type="submit" :disabled="saving" class="rounded-lg bg-[#0061a5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#004f87] disabled:opacity-50">
                    {{ saving ? 'Menyimpan…' : 'Simpan konfigurasi' }}
                </button>
                <span v-if="saveOk" class="ml-3 text-sm font-medium text-emerald-700">Konfigurasi tersimpan.</span>
                <span v-if="saveError" class="ml-3 text-sm text-[#93000a]" role="alert">{{ saveError }}</span>
            </div>
        </form>

        <section class="mt-10 max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Kirim email uji</h2>
            <p class="mt-1 text-xs text-slate-500">Menggunakan konfigurasi tersimpan. Tidak melalui antrean email bisnis.</p>
            <div class="mt-3 flex flex-wrap items-end gap-3">
                <label class="text-sm font-medium text-slate-700">Alamat penerima
                    <input v-model="testRecipient" type="email" class="mt-1 block w-72 max-w-full rounded-lg border-slate-300 text-sm" />
                </label>
                <button
                    type="button"
                    :disabled="testing || !testRecipient"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:border-[#0061a5] disabled:opacity-50"
                    @click="sendTest"
                >
                    {{ testing ? 'Mengirim…' : 'Kirim email uji' }}
                </button>
            </div>
            <p v-if="testResult === 'SUCCESS'" class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                {{ testMessage }}
            </p>
            <p v-else-if="testResult === 'FAILURE'" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800" role="alert">
                {{ testMessage }}
            </p>
        </section>
    </AppShell>
</template>
