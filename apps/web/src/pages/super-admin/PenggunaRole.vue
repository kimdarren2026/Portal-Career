<script setup lang="ts">
/**
 * Super Admin "Pengguna dan Role".
 *
 * Role catalogue (FSD §3.1) + assign/revoke (`POST /admin/users/{user}/roles`
 * and its revoke pair). PGC-V1 / PD-F adds the user directory
 * (`GET /admin/users`) and the `ACTIVE ↔ SUSPENDED` account lifecycle
 * (`POST /admin/users/{user}/suspend|restore`). `DISABLED` stays deferred.
 */
import { Head } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { authRequest, errorText, newIdempotencyKey } from '@/lib/auth'

interface RoleRow { id: number; code: string; name: string; description: string | null }
interface UserRow {
    id: number
    name: string
    email: string
    status: string
    active_roles: string[]
    created_at: string | null
}
interface UsersPage {
    items: UserRow[]
    pagination: { page: number; per_page: number; total: number; last_page: number }
}

const props = defineProps<{ roles: RoleRow[]; users: UsersPage }>()

const form = reactive({ userId: '', roleCode: props.roles[0]?.code ?? '' })
const busy = ref<'' | 'assign' | 'revoke'>('')
const okText = ref('')
const errText = ref('')

const directory = reactive<UsersPage>(props.users)
const filters = reactive({ q: '', status: '', role: '' })
const dirBusy = ref(false)
const dirErr = ref('')
const statusLabels: Record<string, string> = {
    ACTIVE: 'Aktif', SUSPENDED: 'Ditangguhkan', DISABLED: 'Dinonaktifkan',
    PENDING_EMAIL_VERIFICATION: 'Menunggu Verifikasi',
}

function validId(): number | null {
    const n = Number(form.userId)
    return Number.isInteger(n) && n > 0 ? n : null
}

async function submit(kind: 'assign' | 'revoke') {
    okText.value = ''
    errText.value = ''
    const id = validId()
    if (!id) {
        errText.value = 'Masukkan ID pengguna yang valid.'
        return
    }
    busy.value = kind
    const path = kind === 'assign'
        ? `/admin/users/${id}/roles`
        : `/admin/users/${id}/roles/${encodeURIComponent(form.roleCode)}/revoke`
    const body = kind === 'assign' ? { role_code: form.roleCode } : {}
    const headers: Record<string, string> = kind === 'assign' ? { 'Idempotency-Key': newIdempotencyKey() } : {}
    const { response, payload } = await authRequest(path, body, 'POST', headers)
    busy.value = ''
    if (!response.ok) {
        errText.value = errorText(payload)
        return
    }
    okText.value = kind === 'assign'
        ? `Role ${form.roleCode} diberikan ke pengguna #${id}.`
        : ((payload.data as { revoked?: boolean })?.revoked
            ? `Role ${form.roleCode} dicabut dari pengguna #${id}.`
            : `Pengguna #${id} tidak memiliki role ${form.roleCode} yang aktif.`)
    await reloadDirectory()
}

async function reloadDirectory(page = 1) {
    dirBusy.value = true
    dirErr.value = ''
    const qs = new URLSearchParams()
    if (filters.q.trim()) qs.set('q', filters.q.trim())
    if (filters.status) qs.set('status', filters.status)
    if (filters.role) qs.set('role', filters.role)
    if (page > 1) qs.set('page', String(page))
    const response = await fetch(`/admin/users?${qs.toString()}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    const payload = (await response.json()) as { data?: UsersPage; error?: { message?: string } }
    dirBusy.value = false
    if (!response.ok || !payload.data) {
        dirErr.value = errorText(payload)
        return
    }
    directory.items = payload.data.items
    directory.pagination = payload.data.pagination
}

async function lifecycle(user: UserRow, action: 'suspend' | 'restore') {
    let reason = ''
    if (action === 'suspend') {
        reason = window.prompt('Alasan penangguhan (wajib):') ?? ''
        if (!reason.trim()) return
    } else {
        reason = window.prompt('Alasan pemulihan (opsional):') ?? ''
    }
    dirBusy.value = true
    dirErr.value = ''
    const { response, payload } = await authRequest(
        `/admin/users/${user.id}/${action}`,
        reason.trim() ? { reason: reason.trim() } : {},
        'POST',
    )
    dirBusy.value = false
    if (!response.ok) {
        dirErr.value = errorText(payload)
        return
    }
    await reloadDirectory(directory.pagination.page)
}
</script>

<template>
    <Head title="Pengguna dan Role" />
    <AppShell persona="super-admin" active="pengguna-role" title="Pengguna dan Role">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Pengguna dan Role</h1>
        <p class="mt-2 max-w-2xl text-slate-600">
            Katalog role sistem bersifat <span class="font-semibold">tetap</span> (FSD §3.1). Direktori pengguna,
            pemberian/pencabutan role, dan penangguhan/pemulihan akun tersedia.
        </p>

        <section class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Kode Role</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Deskripsi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="r in roles" :key="r.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ r.code }}</td>
                        <td class="px-4 py-3 font-medium text-[#002045]">{{ r.name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ r.description ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Direktori Pengguna</h2>
            <form class="mt-3 flex flex-wrap items-end gap-3" @submit.prevent="reloadDirectory(1)">
                <label class="text-sm font-medium text-slate-700">Cari
                    <input v-model="filters.q" class="mt-1 block w-56 rounded-lg border-slate-300 text-sm" placeholder="nama atau email" />
                </label>
                <label class="text-sm font-medium text-slate-700">Status
                    <select v-model="filters.status" class="mt-1 block w-48 rounded-lg border-slate-300 text-sm">
                        <option value="">Semua</option>
                        <option value="ACTIVE">Aktif</option>
                        <option value="SUSPENDED">Ditangguhkan</option>
                        <option value="PENDING_EMAIL_VERIFICATION">Menunggu Verifikasi</option>
                        <option value="DISABLED">Dinonaktifkan</option>
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">Role
                    <select v-model="filters.role" class="mt-1 block w-56 rounded-lg border-slate-300 text-sm">
                        <option value="">Semua</option>
                        <option v-for="r in roles" :key="r.id" :value="r.code">{{ r.code }}</option>
                    </select>
                </label>
                <button type="submit" :disabled="dirBusy" class="rounded-lg bg-[#0061a5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#004f87] disabled:opacity-50">
                    {{ dirBusy ? 'Memuat…' : 'Terapkan' }}
                </button>
            </form>
            <p v-if="dirErr" class="mt-3 text-sm text-[#93000a]" role="alert">{{ dirErr }}</p>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">ID</th>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Role Aktif</th>
                            <th class="px-4 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="u in directory.items" :key="u.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ u.id }}</td>
                            <td class="px-4 py-3 font-medium text-[#002045]">{{ u.name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ u.email }}</td>
                            <td class="px-4 py-3">{{ statusLabels[u.status] ?? u.status }}</td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ u.active_roles.join(', ') || '—' }}</td>
                            <td class="px-4 py-3">
                                <button v-if="u.status === 'ACTIVE'" :disabled="dirBusy" class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-semibold text-[#93000a] hover:border-[#93000a] disabled:opacity-50" @click="lifecycle(u, 'suspend')">Tangguhkan</button>
                                <button v-else-if="u.status === 'SUSPENDED'" :disabled="dirBusy" class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-700 disabled:opacity-50" @click="lifecycle(u, 'restore')">Pulihkan</button>
                                <span v-else class="text-xs text-slate-400">—</span>
                            </td>
                        </tr>
                        <tr v-if="directory.items.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">Tidak ada pengguna yang cocok.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
                <span>Total {{ directory.pagination.total }} pengguna</span>
                <span class="flex gap-2">
                    <button :disabled="dirBusy || directory.pagination.page <= 1" class="rounded border border-slate-300 px-2 py-1 disabled:opacity-40" @click="reloadDirectory(directory.pagination.page - 1)">Sebelumnya</button>
                    <span>Halaman {{ directory.pagination.page }} / {{ directory.pagination.last_page }}</span>
                    <button :disabled="dirBusy || directory.pagination.page >= directory.pagination.last_page" class="rounded border border-slate-300 px-2 py-1 disabled:opacity-40" @click="reloadDirectory(directory.pagination.page + 1)">Berikutnya</button>
                </span>
            </div>
        </section>

        <section class="mt-8 max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Kelola role pengguna</h2>
            <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="submit('assign')">
                <label class="text-sm font-medium text-slate-700">ID Pengguna
                    <input v-model="form.userId" inputmode="numeric" class="mt-1 block w-40 rounded-lg border-slate-300 text-sm" placeholder="mis. 42" />
                </label>
                <label class="text-sm font-medium text-slate-700">Role
                    <select v-model="form.roleCode" class="mt-1 block w-64 rounded-lg border-slate-300 text-sm">
                        <option v-for="r in roles" :key="r.id" :value="r.code">{{ r.code }} — {{ r.name }}</option>
                    </select>
                </label>
                <button type="submit" :disabled="busy !== ''" class="rounded-lg bg-[#0061a5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#004f87] disabled:opacity-50">
                    {{ busy === 'assign' ? 'Memberikan…' : 'Berikan role' }}
                </button>
                <button type="button" :disabled="busy !== ''" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-[#0061a5] disabled:opacity-50" @click="submit('revoke')">
                    {{ busy === 'revoke' ? 'Mencabut…' : 'Cabut role' }}
                </button>
            </form>
            <p v-if="okText" class="mt-3 text-sm font-medium text-emerald-700">{{ okText }}</p>
            <p v-if="errText" class="mt-3 text-sm text-[#93000a]" role="alert">{{ errText }}</p>
        </section>

        <section class="mt-8 max-w-2xl rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-500">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Belum tersedia</h2>
            <p class="mt-2">Status akun <code>DISABLED</code> (penonaktifan permanen) belum termasuk lingkup MVP.</p>
        </section>
    </AppShell>
</template>
