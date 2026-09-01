<script setup lang="ts">
/**
 * Recruiter "Pengaturan Akun" (Frontend Vertical Slice v6).
 *
 * Read + password change + resend verification + sign out only. Account
 * identity (name, email) is displayed read-only — no authenticated
 * email-address or name change contract exists, so none is offered. Every
 * mutation posts to a frozen JSON route; error contracts
 * (AUTH_CURRENT_PASSWORD_INVALID, AUTH_PASSWORD_POLICY, VALIDATION_FAILED)
 * are rendered, never invented.
 */
import { Head } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { authRequest, errorText } from '@/lib/auth'

interface Membership { id: number; name: string; company_role: string; verification_status: string }

const props = defineProps<{
    account: { name: string; email: string; email_verified_at: string | null; status: string }
    roles: string[]
    company_memberships: Membership[]
}>()

const statusLabelMap: Record<string, string> = {
    ACTIVE: 'Aktif',
    PENDING_EMAIL_VERIFICATION: 'Menunggu Verifikasi Email',
    SUSPENDED: 'Ditangguhkan',
    DISABLED: 'Dinonaktifkan',
}
const roleLabelMap: Record<string, string> = {
    COMPANY_ADMIN: 'Admin Perusahaan',
    COMPANY_RECRUITER: 'Recruiter',
    SUPER_ADMIN: 'Super Admin',
}
function roleLabel(r: string): string {
    return roleLabelMap[r] ?? r
}

/* ---- password change ---- */
const pw = reactive({ current_password: '', password: '', password_confirmation: '' })
const pwSaving = ref(false)
const pwMessage = ref('')
const pwSuccess = ref('')
const pwFieldErrors = ref<Record<string, string[]>>({})

async function changePassword() {
    pwSaving.value = true
    pwMessage.value = ''
    pwSuccess.value = ''
    pwFieldErrors.value = {}
    const { response, payload } = await authRequest('/me/password', { ...pw }, 'PUT')
    pwSaving.value = false
    if (!response.ok) {
        pwFieldErrors.value = payload.error?.details?.fields ?? {}
        pwMessage.value =
            payload.error?.code === 'AUTH_CURRENT_PASSWORD_INVALID'
                ? 'Password saat ini tidak cocok.'
                : payload.error?.code === 'AUTH_PASSWORD_POLICY'
                    ? 'Password baru belum memenuhi ketentuan keamanan.'
                    : errorText(payload)
        return
    }
    pw.current_password = ''
    pw.password = ''
    pw.password_confirmation = ''
    pwSuccess.value = 'Password diperbarui. Sesi lain pada akun ini telah dikeluarkan.'
}

/* ---- resend verification ---- */
const resendState = ref<'idle' | 'sending' | 'sent' | 'error'>('idle')
async function resendVerification() {
    resendState.value = 'sending'
    const { response } = await authRequest('/auth/resend-verification', { email: props.account.email }, 'POST')
    resendState.value = response.ok ? 'sent' : 'error'
}

/* ---- sign out ---- */
const signingOut = ref(false)
async function signOut() {
    signingOut.value = true
    await authRequest('/auth/logout', {}, 'POST')
    window.location.assign('/login')
}
</script>

<template>
    <Head title="Pengaturan Akun" />
    <AppShell persona="recruiter" active="pengaturan-akun" title="Pengaturan Akun">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Pengaturan Akun</h1>
        <p class="mt-2 max-w-2xl text-slate-600">Kelola informasi akun dan keamanan Anda.</p>

        <!-- Account information -->
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-[#002045]">Informasi Akun</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nama</dt>
                    <dd class="mt-1 text-sm text-[#181c1e]">{{ account.name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email</dt>
                    <dd class="mt-1 flex flex-wrap items-center gap-2 text-sm text-[#181c1e]">
                        {{ account.email }}
                        <span
                            v-if="account.email_verified_at"
                            class="rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-semibold text-green-800"
                        >Terverifikasi</span>
                        <span
                            v-else
                            class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800"
                        >Belum Terverifikasi</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status Akun</dt>
                    <dd class="mt-1 text-sm text-[#181c1e]">{{ statusLabelMap[account.status] ?? account.status }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Peran</dt>
                    <dd class="mt-1 text-sm text-[#181c1e]">{{ roles.map(roleLabel).join(', ') || '—' }}</dd>
                </div>
            </dl>

            <div v-if="!account.email_verified_at" class="mt-4">
                <button
                    type="button"
                    :disabled="resendState === 'sending' || resendState === 'sent'"
                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-[#0061a5] disabled:opacity-60"
                    @click="resendVerification"
                >
                    {{ resendState === 'sent' ? 'Email verifikasi terkirim' : resendState === 'sending' ? 'Mengirim…' : 'Kirim ulang email verifikasi' }}
                </button>
                <p v-if="resendState === 'error'" class="mt-1 text-xs text-red-600">Gagal mengirim. Coba lagi nanti.</p>
            </div>

            <p class="mt-4 text-xs text-slate-400">
                Perubahan nama atau alamat email belum tersedia melalui pengaturan akun.
            </p>
        </section>

        <!-- Company memberships -->
        <section v-if="company_memberships.length" class="mt-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-[#002045]">Keanggotaan Perusahaan</h2>
            <ul class="mt-3 space-y-2 text-sm text-slate-700">
                <li v-for="m in company_memberships" :key="m.id" class="flex flex-wrap items-center gap-2">
                    <span class="font-medium text-[#002045]">{{ m.name }}</span>
                    <span class="text-slate-400">·</span>
                    <span>{{ roleLabel(m.company_role) }}</span>
                </li>
            </ul>
        </section>

        <!-- Password change -->
        <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-[#002045]">Ubah Password</h2>
            <p class="mt-1 text-sm text-slate-600">
                Minimal 8 karakter dengan huruf besar, huruf kecil, dan angka. Mengubah password akan mengeluarkan sesi lain pada akun ini.
            </p>

            <p v-if="pwSuccess" class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800" role="status">{{ pwSuccess }}</p>
            <p v-if="pwMessage" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-[#93000a]" role="alert">{{ pwMessage }}</p>

            <form class="mt-4 max-w-md space-y-4" @submit.prevent="changePassword">
                <label class="block text-sm font-medium text-slate-700">
                    Password Saat Ini
                    <input
                        v-model="pw.current_password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]"
                    />
                    <span v-if="pwFieldErrors.current_password" class="mt-1 block text-xs text-red-600">{{ pwFieldErrors.current_password[0] }}</span>
                </label>
                <label class="block text-sm font-medium text-slate-700">
                    Password Baru
                    <input
                        v-model="pw.password"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]"
                    />
                    <span v-if="pwFieldErrors.password" class="mt-1 block text-xs text-red-600">{{ pwFieldErrors.password[0] }}</span>
                </label>
                <label class="block text-sm font-medium text-slate-700">
                    Konfirmasi Password Baru
                    <input
                        v-model="pw.password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]"
                    />
                </label>
                <button
                    type="submit"
                    :disabled="pwSaving"
                    class="rounded-lg bg-[#0061a5] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#004172] disabled:opacity-60"
                >
                    {{ pwSaving ? 'Menyimpan…' : 'Ubah Password' }}
                </button>
            </form>
        </section>

        <!-- Session -->
        <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-[#002045]">Sesi</h2>
            <p class="mt-1 text-sm text-slate-600">Keluar dari sesi ini di perangkat yang Anda gunakan sekarang.</p>
            <button
                type="button"
                :disabled="signingOut"
                class="mt-4 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-red-300 hover:text-[#93000a] disabled:opacity-60"
                @click="signOut"
            >
                {{ signingOut ? 'Keluar…' : 'Keluar' }}
            </button>
        </section>
    </AppShell>
</template>
