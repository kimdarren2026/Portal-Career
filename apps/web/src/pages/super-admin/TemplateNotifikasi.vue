<script setup lang="ts">
/**
 * Super Admin "Template Notifikasi" — READ_ONLY_REFERENCE.
 *
 * No notification-template entity and no frozen notification-type vocabulary
 * exist (`API_CONTRACT.md` Part X item 59). The only deterministic source is
 * the frozen FR-NOTIF-002 trigger → recipient table, shown verbatim. No
 * template editing, no invented per-type labels.
 */
import { Head } from '@inertiajs/vue3'
import AppShell from '@/layouts/AppShell.vue'

interface Trigger { trigger: string; recipient: string }
defineProps<{ triggers: Trigger[] }>()
</script>

<template>
    <Head title="Template Notifikasi" />
    <AppShell persona="super-admin" active="template-notifikasi" title="Template Notifikasi">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Template Notifikasi</h1>
        <p class="mt-2 max-w-2xl text-slate-600">
            Pemicu notifikasi/email utama yang didefinisikan sistem (FSD FR-NOTIF-002). Bersifat
            <span class="font-semibold">hanya-baca</span> — tidak ada penyuntingan template dan tidak ada
            kosakata jenis notifikasi yang dibekukan.
        </p>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Pemicu</th>
                        <th class="px-4 py-3">Penerima</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="t in triggers" :key="t.trigger" class="align-top hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-[#002045]">{{ t.trigger }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ t.recipient }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="mt-4 text-xs text-slate-400">
            Isi/badan template dan pemetaan channel per-pemicu belum memiliki kontrak; halaman ini tidak
            menampilkan label jenis notifikasi yang belum dibekukan.
        </p>
    </AppShell>
</template>
