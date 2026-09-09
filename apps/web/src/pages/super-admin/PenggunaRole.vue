<script setup lang="ts">
/**
 * Super Admin "Pengguna dan Role" — PARTIAL_FUNCTIONAL.
 *
 * Deterministic today: the frozen role catalogue (FSD §3.1) and the assign /
 * revoke operations (`POST /admin/users/{user}/roles` and its revoke pair —
 * frozen contract: Idempotency-Key, 409 on an active duplicate, audit
 * `role_changed`, affected user notified).
 *
 * NOT available: a browsable user directory. `GET /admin/users` has no frozen
 * field / filter / pagination contract, and suspend / restore / DISABLED
 * session-termination semantics are unresolved. Those controls are shown as
 * explicitly unavailable — not as "Segera hadir".
 */
import { Head } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { authRequest, errorText, newIdempotencyKey } from '@/lib/auth'

interface RoleRow { id: number; code: string; name: string; description: string | null }
const props = defineProps<{ roles: RoleRow[] }>()

const form = reactive({ userId: '', roleCode: props.roles[0]?.code ?? '' })
const busy = ref<'' | 'assign' | 'revoke'>('')
const okText = ref('')
const errText = ref('')

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
}
</script>

<template>
    <Head title="Pengguna dan Role" />
    <AppShell persona="super-admin" active="pengguna-role" title="Pengguna dan Role">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Pengguna dan Role</h1>
        <p class="mt-2 max-w-2xl text-slate-600">
            Katalog role sistem bersifat <span class="font-semibold">tetap</span> (FSD §3.1). Pemberian dan
            pencabutan role tersedia; direktori pengguna dan penangguhan/pemulihan akun belum memiliki
            kontrak yang final.
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

        <section class="mt-8 max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Kelola role pengguna</h2>
            <p class="mt-1 text-xs text-slate-500">
                Masukkan ID pengguna yang sudah diketahui. Pencarian/penelusuran daftar pengguna belum tersedia.
            </p>
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
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>Direktori pengguna (daftar, filter, halaman) — kontrak <code>GET /admin/users</code> belum final.</li>
                <li>Penangguhan / pemulihan akun dan status <code>DISABLED</code> — semantik pemutusan sesi belum final.</li>
            </ul>
        </section>
    </AppShell>
</template>
