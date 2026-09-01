<script setup lang="ts">
/**
 * Recruiter "Notifikasi" (Frontend Vertical Slice v7) — the browser
 * realization of GET /notifications (API_CONTRACT.md Part IX). OWN by
 * user_id; company membership never widens the set.
 *
 * The list, `unread_count` and pagination come from NotificationPageController
 * (frozen ListNotifications / NotificationScope / NotificationPresenter).
 * "Tandai dibaca" and "Tandai semua sudah dibaca" post to the frozen
 * `/notifications/{id}/read` and `/notifications/read-all` routes (204).
 *
 * `type` / `title` are opaque stable codes from the domain notifiers — the
 * page displays the stored `title` with cosmetic formatting only (lower-case,
 * underscores to spaces); it invents no vocabulary and no Indonesian label,
 * and an unknown/future code formats identically. A row links to a deep
 * destination only when the server resolved one (`link`); the target route
 * enforces its own authorization.
 */
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { authRequest, errorText } from '@/lib/auth'
import { formatDateTime } from '@/lib/labels'

interface NotificationRow {
    id: number
    type: string
    title: string
    body_reference: string | null
    related_object_type: string | null
    related_object_id: number | null
    read_at: string | null
    created_at: string | null
    link: string | null
}
interface Pagination { page: number; per_page: number; total: number; last_page: number }

const props = defineProps<{
    items: NotificationRow[]
    pagination: Pagination
    filters: Record<string, string>
    unread_count: number
}>()

const unreadOnly = ref(props.filters.read === 'false' || props.filters.read === '0')
const banner = ref('')
const busyId = ref<number | null>(null)
const busyAll = ref(false)

/** Cosmetic only — formats the stored opaque code, never translates it. */
function formatTitle(raw: string): string {
    const s = raw.replace(/_/g, ' ').toLowerCase().trim()
    return s.charAt(0).toUpperCase() + s.slice(1)
}

function applyFilter() {
    const query: Record<string, string> = {}
    if (unreadOnly.value) query.read = 'false'
    router.get('/notifikasi', query, { preserveState: true, replace: true })
}

function goToPage(page: number) {
    const query: Record<string, string | number> = { page }
    if (unreadOnly.value) query.read = 'false'
    router.get('/notifikasi', query, { preserveState: true, replace: true })
}

async function markRead(row: NotificationRow) {
    if (row.read_at) return
    busyId.value = row.id
    banner.value = ''
    const { response, payload } = await authRequest(`/notifications/${row.id}/read`, {}, 'POST')
    busyId.value = null
    if (!response.ok && response.status !== 204) {
        banner.value = errorText(payload)
        return
    }
    router.reload({ only: ['items', 'unread_count', 'pagination'] })
}

async function markAllRead() {
    busyAll.value = true
    banner.value = ''
    const { response, payload } = await authRequest('/notifications/read-all', {}, 'POST')
    busyAll.value = false
    if (!response.ok && response.status !== 204) {
        banner.value = errorText(payload)
        return
    }
    router.reload({ only: ['items', 'unread_count', 'pagination'] })
}

async function openRow(row: NotificationRow) {
    if (!row.read_at) {
        await authRequest(`/notifications/${row.id}/read`, {}, 'POST')
    }
    if (row.link) router.visit(row.link)
    else router.reload({ only: ['items', 'unread_count'] })
}

const hasPages = computed(() => props.pagination.last_page > 1)
</script>

<template>
    <Head title="Notifikasi" />
    <AppShell persona="recruiter" active="notifikasi" title="Notifikasi">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Notifikasi</h1>
                <p class="mt-2 text-slate-600">
                    {{ unread_count > 0 ? `${unread_count} notifikasi belum dibaca` : 'Semua notifikasi sudah dibaca' }}
                </p>
            </div>
            <button
                type="button"
                :disabled="busyAll || unread_count === 0"
                class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:border-[#0061a5] disabled:opacity-50"
                @click="markAllRead"
            >
                {{ busyAll ? 'Memproses…' : 'Tandai semua sudah dibaca' }}
            </button>
        </div>

        <p v-if="banner" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-[#93000a]" role="alert">{{ banner }}</p>

        <label class="mt-6 flex w-fit items-center gap-2 text-sm font-medium text-slate-700">
            <input type="checkbox" v-model="unreadOnly" class="rounded border-slate-300 text-[#0061a5] focus:ring-[#0061a5]" @change="applyFilter" />
            Hanya yang belum dibaca
        </label>

        <div v-if="items.length === 0" class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">
            {{ unreadOnly ? 'Tidak ada notifikasi yang belum dibaca.' : 'Belum ada notifikasi.' }}
        </div>

        <ul v-else class="mt-6 space-y-2">
            <li
                v-for="row in items"
                :key="row.id"
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition"
                :class="{ 'border-l-4 border-l-[#0061a5]': !row.read_at }"
            >
                <div class="flex items-start justify-between gap-4">
                    <component
                        :is="row.link ? 'button' : 'div'"
                        class="min-w-0 flex-1 text-left"
                        :class="row.link ? 'cursor-pointer' : ''"
                        @click="row.link ? openRow(row) : undefined"
                    >
                        <p class="flex items-center gap-2 font-semibold text-[#002045]">
                            <span v-if="!row.read_at" class="inline-block h-2 w-2 shrink-0 rounded-full bg-[#0061a5]" aria-label="Belum dibaca" />
                            <span class="truncate">{{ formatTitle(row.title) }}</span>
                        </p>
                        <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                            <span class="rounded bg-slate-100 px-1.5 py-0.5 font-medium tracking-wide text-slate-600">{{ row.type }}</span>
                            <span>{{ formatDateTime(row.created_at) }}</span>
                            <span v-if="row.link" class="font-semibold text-[#0061a5]">Lihat detail →</span>
                        </p>
                    </component>
                    <button
                        v-if="!row.read_at"
                        type="button"
                        :disabled="busyId === row.id"
                        class="shrink-0 rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:border-[#0061a5] disabled:opacity-50"
                        @click="markRead(row)"
                    >
                        Tandai dibaca
                    </button>
                </div>
            </li>
        </ul>

        <div v-if="hasPages" class="mt-6 flex items-center justify-between text-sm">
            <button
                type="button"
                :disabled="pagination.page <= 1"
                class="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold text-slate-700 disabled:opacity-40"
                @click="goToPage(pagination.page - 1)"
            >
                Sebelumnya
            </button>
            <span class="text-slate-500">Halaman {{ pagination.page }} dari {{ pagination.last_page }}</span>
            <button
                type="button"
                :disabled="pagination.page >= pagination.last_page"
                class="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold text-slate-700 disabled:opacity-40"
                @click="goToPage(pagination.page + 1)"
            >
                Berikutnya
            </button>
        </div>
    </AppShell>
</template>
