<script setup lang="ts">
/**
 * Super Admin "Retensi Data" — READ_ONLY_REFERENCE.
 *
 * Renders the effective frozen policy (FSD FR-AUD-002). No retention period is
 * invented, and there is no purge / anonymize control — the operational
 * deletion/anonymization workflow is not currently configured.
 */
import { Head } from '@inertiajs/vue3'
import AppShell from '@/layouts/AppShell.vue'

interface Policy {
    recruitment_data_retention: string
    user_hard_delete: boolean
    deletion_channel: string
    operational_workflow_configured: boolean
    statements: string[]
}
defineProps<{ policy: Policy }>()
</script>

<template>
    <Head title="Retensi Data" />
    <AppShell persona="super-admin" active="retensi-data" title="Retensi Data">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Retensi Data</h1>
        <p class="mt-2 max-w-2xl text-slate-600">
            Kebijakan retensi data yang berlaku (FSD FR-AUD-002). Bersifat <span class="font-semibold">hanya-baca</span>.
        </p>

        <dl class="mt-6 grid max-w-3xl gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Retensi data rekrutmen</dt>
                <dd class="mt-1 text-lg font-bold text-[#002045]">Tanpa batas waktu</dd>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hapus permanen oleh pengguna</dt>
                <dd class="mt-1 text-lg font-bold text-[#002045]">{{ policy.user_hard_delete ? 'Diizinkan' : 'Tidak diizinkan' }}</dd>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Alur operasional</dt>
                <dd class="mt-1 text-lg font-bold text-[#002045]">{{ policy.operational_workflow_configured ? 'Dikonfigurasi' : 'Belum dikonfigurasi' }}</dd>
            </div>
        </dl>

        <ul class="mt-6 max-w-3xl list-disc space-y-2 rounded-2xl border border-slate-200 bg-white p-6 pl-10 text-sm text-slate-600 shadow-sm">
            <li v-for="(s, i) in policy.statements" :key="i">{{ s }}</li>
        </ul>
    </AppShell>
</template>
