<script setup lang="ts">
/**
 * Recruiter "Anggota Perusahaan" (Frontend Vertical Slice v5) — FR-COMP-004,
 * closed D-1.
 *
 * Read + add + change-role + revoke/leave only. The member read model is the
 * frozen one: `user_id`, `company_role`, `status`, `joined_at` — no name or
 * email is ever delivered, so a member is shown as "Pengguna #<id>" (and
 * "Anda" for the signed-in user's own row). Every mutation posts to the
 * frozen `/companies/{id}/members` JSON routes; the last-active-admin
 * invariant and "unknown email is NOT SUPPORTED" are backend authority —
 * this page only renders their error contracts.
 */
import { Head } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppShell from '@/layouts/AppShell.vue'
import { authRequest, errorText } from '@/lib/auth'

interface Member {
    id: number
    user_id: number
    company_role: string
    status: string
    joined_at: string | null
    revoked_at: string | null
}

const props = defineProps<{
    company: { id: number; name: string }
    members: Member[]
    can_manage: boolean
    current_user_id: number
    roles: string[]
}>()

const roleLabel: Record<string, string> = {
    COMPANY_ADMIN: 'Admin Perusahaan',
    COMPANY_RECRUITER: 'Recruiter',
}

const activeAdminCount = computed(
    () => props.members.filter((m) => m.company_role === 'COMPANY_ADMIN').length,
)

function label(role: string): string {
    return roleLabel[role] ?? role
}

function fmtDate(iso: string | null): string {
    if (!iso) return '—'
    return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

/* ---- error mapping ---- */
const banner = ref('')

function mapError(code: string | undefined, fallback: string): string {
    switch (code) {
        case 'MEMBER_LAST_ADMIN':
            return 'Setidaknya satu Admin Perusahaan aktif harus dipertahankan.'
        case 'MEMBER_ALREADY_ACTIVE':
            return 'Pengguna ini sudah menjadi anggota aktif perusahaan.'
        case 'NOT_FOUND':
            return 'Akun dengan email tersebut tidak ditemukan. Anggota baru harus sudah memiliki akun terdaftar di portal.'
        case 'AUTH_EMAIL_NOT_VERIFIED':
            return 'Verifikasi email akun Anda terlebih dahulu sebelum mengelola anggota.'
        case 'AUTH_FORBIDDEN':
            return 'Anda tidak berhak melakukan tindakan ini.'
        default:
            return fallback
    }
}

/* ---- add member ---- */
const addForm = reactive({ email: '', company_role: props.roles[0] ?? 'COMPANY_RECRUITER' })
const adding = ref(false)
const addFieldErrors = ref<Record<string, string[]>>({})

async function addMember() {
    adding.value = true
    banner.value = ''
    addFieldErrors.value = {}
    const { response, payload } = await authRequest(
        `/companies/${props.company.id}/members`,
        { email: addForm.email, company_role: addForm.company_role },
        'POST',
    )
    adding.value = false
    if (!response.ok) {
        addFieldErrors.value = payload.error?.details?.fields ?? {}
        banner.value = mapError(payload.error?.code, errorText(payload))
        return
    }
    addForm.email = ''
    router.reload()
}

/* ---- change role ---- */
const busyMemberId = ref<number | null>(null)

async function changeRole(member: Member, role: string) {
    if (role === member.company_role) return
    busyMemberId.value = member.id
    banner.value = ''
    const { response, payload } = await authRequest(
        `/companies/${props.company.id}/members/${member.id}`,
        { company_role: role },
        'PATCH',
    )
    busyMemberId.value = null
    if (!response.ok) {
        banner.value = mapError(payload.error?.code, errorText(payload))
        router.reload() // resync the <select> back to the stored value
        return
    }
    router.reload()
}

/* ---- revoke / leave ---- */
async function revoke(member: Member) {
    const isSelf = member.user_id === props.current_user_id
    const confirmText = isSelf
        ? `Keluar dari ${props.company.name}? Anda akan kehilangan akses ruang kerja perusahaan ini.`
        : `Keluarkan Pengguna #${member.user_id} dari perusahaan?`
    if (!window.confirm(confirmText)) return

    busyMemberId.value = member.id
    banner.value = ''
    const { response, payload } = await authRequest(
        `/companies/${props.company.id}/members/${member.id}`,
        {},
        'DELETE',
    )
    busyMemberId.value = null
    if (!response.ok) {
        banner.value = mapError(payload.error?.code, errorText(payload))
        return
    }
    if (isSelf) {
        window.location.assign('/dashboard')
        return
    }
    router.reload()
}
</script>

<template>
    <Head title="Anggota Perusahaan" />
    <AppShell persona="recruiter" active="anggota-perusahaan" title="Anggota Perusahaan">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Anggota Perusahaan</h1>
        <p class="mt-2 max-w-2xl text-slate-600">
            Anggota aktif {{ company.name }}. Hanya anggota aktif yang memiliki akses ruang kerja perusahaan.
        </p>

        <p v-if="banner" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-[#93000a]" role="alert">
            {{ banner }}
        </p>

        <p
            v-if="!can_manage"
            class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600"
        >
            Pengelolaan anggota adalah wewenang Admin Perusahaan. Anda dapat melihat daftar anggota dan keluar dari perusahaan.
        </p>

        <!-- Member list -->
        <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Anggota</th>
                        <th class="px-5 py-3">Peran</th>
                        <th class="px-5 py-3">Bergabung</th>
                        <th class="px-5 py-3 text-right">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="member in members" :key="member.id">
                        <td class="px-5 py-4 font-medium text-[#002045]">
                            Pengguna #{{ member.user_id }}
                            <span
                                v-if="member.user_id === current_user_id"
                                class="ml-2 rounded-full bg-[#66affe]/20 px-2 py-0.5 text-[11px] font-semibold text-[#004172]"
                            >Anda</span>
                        </td>
                        <td class="px-5 py-4">
                            <select
                                v-if="can_manage"
                                :value="member.company_role"
                                :disabled="busyMemberId === member.id"
                                class="rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]"
                                @change="changeRole(member, ($event.target as HTMLSelectElement).value)"
                            >
                                <option v-for="r in roles" :key="r" :value="r">{{ label(r) }}</option>
                            </select>
                            <span v-else class="font-medium text-slate-700">{{ label(member.company_role) }}</span>
                        </td>
                        <td class="px-5 py-4 text-slate-600">{{ fmtDate(member.joined_at) }}</td>
                        <td class="px-5 py-4 text-right">
                            <button
                                v-if="can_manage || member.user_id === current_user_id"
                                type="button"
                                :disabled="busyMemberId === member.id"
                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-red-300 hover:text-[#93000a] disabled:opacity-50"
                                @click="revoke(member)"
                            >
                                {{ member.user_id === current_user_id ? 'Keluar' : 'Keluarkan' }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-slate-400">
            {{ activeAdminCount }} Admin Perusahaan aktif. Nama dan email anggota tidak ditampilkan sesuai kebijakan privasi;
            anggota diidentifikasi dengan ID pengguna.
        </p>

        <!-- Add member -->
        <form
            v-if="can_manage"
            class="mt-8 space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
            @submit.prevent="addMember"
        >
            <h2 class="text-lg font-semibold text-[#002045]">Tambah Anggota</h2>
            <p class="text-sm text-slate-600">
                Masukkan email akun yang <strong>sudah terdaftar</strong> di portal. Anggota baru tetap memverifikasi akunnya
                melalui mekanisme keamanan standar.
            </p>
            <div class="grid gap-4 sm:grid-cols-[1fr_auto_auto] sm:items-end">
                <label class="block text-sm font-medium text-slate-700">
                    Email Anggota
                    <input
                        v-model="addForm.email"
                        type="email"
                        required
                        class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]"
                        placeholder="nama@perusahaan.com"
                    />
                    <span v-if="addFieldErrors.email" class="mt-1 block text-xs text-red-600">{{ addFieldErrors.email[0] }}</span>
                </label>
                <label class="block text-sm font-medium text-slate-700">
                    Peran
                    <select
                        v-model="addForm.company_role"
                        class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]"
                    >
                        <option v-for="r in roles" :key="r" :value="r">{{ label(r) }}</option>
                    </select>
                    <span v-if="addFieldErrors.company_role" class="mt-1 block text-xs text-red-600">{{ addFieldErrors.company_role[0] }}</span>
                </label>
                <button
                    type="submit"
                    :disabled="adding"
                    class="rounded-lg bg-[#0061a5] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#004172] disabled:opacity-60"
                >
                    {{ adding ? 'Menambahkan…' : 'Tambah' }}
                </button>
            </div>
        </form>
    </AppShell>
</template>
