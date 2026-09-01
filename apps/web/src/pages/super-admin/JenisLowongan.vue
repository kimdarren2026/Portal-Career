<script setup lang="ts">
/**
 * Super Admin "Jenis Lowongan" (Frontend Vertical Slice v12) — a READ-ONLY
 * reference over the frozen `VacancyType` enum (PO decision
 * SUPER_ADMIN_VACANCY_TYPE_REFERENCE_MVP — API_CONTRACT.md Part X item 61).
 *
 * These are fixed system types for MVP. There is deliberately no Tambah /
 * Ubah / Hapus / Aktif-Nonaktif / reorder control — the page communicates
 * that the vocabulary is fixed. Labels reuse the frozen FE-3 map
 * (`vacancyTypeLabel`, Part X item 49); `ownership` is the source-backed
 * classification from the server (INV-018).
 */
import { Head } from '@inertiajs/vue3'
import AppShell from '@/layouts/AppShell.vue'
import { vacancyTypeLabel, statusLabel } from '@/lib/labels'

interface VacancyTypeRow { code: string; ownership: string }
defineProps<{ types: VacancyTypeRow[] }>()

const ownershipLabel: Record<string, string> = {
    CAMPUS: 'Kampus (unit organisasi)',
    COMPANY: 'Perusahaan',
}
</script>

<template>
    <Head title="Jenis Lowongan" />
    <AppShell persona="super-admin" active="jenis-lowongan" title="Jenis Lowongan">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Jenis Lowongan</h1>
        <p class="mt-2 max-w-2xl text-slate-600">
            Referensi jenis lowongan sistem. Untuk MVP jenis lowongan bersifat
            <span class="font-semibold">tetap</span> dan tidak dapat ditambah, diubah, dihapus,
            dinonaktifkan, atau diurutkan ulang.
        </p>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Kode Sistem</th>
                        <th class="px-4 py-3">Label</th>
                        <th class="px-4 py-3">Kepemilikan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="t in types" :key="t.code">
                        <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ t.code }}</td>
                        <td class="px-4 py-3 font-medium text-[#002045]">{{ statusLabel(vacancyTypeLabel, t.code) }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ ownershipLabel[t.ownership] ?? t.ownership }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="mt-4 text-xs text-slate-400">
            Jenis lowongan yang dapat dikonfigurasi ditangguhkan setelah MVP.
        </p>
    </AppShell>
</template>
