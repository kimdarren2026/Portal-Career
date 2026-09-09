<script setup lang="ts">
/**
 * Super Admin "Integrasi" — READ_ONLY_REFERENCE.
 *
 * Truthful capability/status only. No integration record, connector registry,
 * or credential form. Alumni-verification source is an OPEN decision
 * (`API_CONTRACT.md` Part X item 1 / backlog D-3); no two-way ATS integration
 * is in the frozen MVP. No secret is ever shown.
 */
import { Head } from '@inertiajs/vue3'
import AppShell from '@/layouts/AppShell.vue'

interface Capability { key: string; label: string; status: string; detail: string }
defineProps<{ capabilities: Capability[] }>()

const statusLabel: Record<string, string> = {
    PENDING_DECISION: 'Menunggu keputusan',
    NOT_IN_MVP: 'Di luar MVP',
    AVAILABLE: 'Tersedia',
    NOT_CONFIGURED: 'Tidak dikonfigurasi',
}
const statusClass: Record<string, string> = {
    PENDING_DECISION: 'bg-amber-100 text-amber-800',
    NOT_IN_MVP: 'bg-slate-100 text-slate-600',
    AVAILABLE: 'bg-emerald-100 text-emerald-800',
    NOT_CONFIGURED: 'bg-slate-100 text-slate-600',
}
</script>

<template>
    <Head title="Integrasi" />
    <AppShell persona="super-admin" active="integrasi" title="Integrasi">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Integrasi</h1>
        <p class="mt-2 max-w-2xl text-slate-600">
            Status kemampuan integrasi sistem. Tidak ada konektor yang dapat dikonfigurasi dan tidak ada
            kredensial yang ditampilkan.
        </p>

        <div class="mt-6 space-y-3">
            <div v-for="c in capabilities" :key="c.key" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-[#002045]">{{ c.label }}</h2>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusClass[c.status] ?? 'bg-slate-100 text-slate-600'">
                        {{ statusLabel[c.status] ?? c.status }}
                    </span>
                </div>
                <p class="mt-2 text-sm text-slate-600">{{ c.detail }}</p>
            </div>
        </div>
    </AppShell>
</template>
