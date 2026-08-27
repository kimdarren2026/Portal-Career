<script setup lang="ts">
/**
 * Shared authenticated nav shell (Frontend Vertical Slice v1). Preserves the
 * full canonical candidate/recruiter menu — items this slice does not yet
 * implement render as a disabled "Segera hadir" entry rather than a dead
 * link, per the no-dead-link requirement. Visual structure follows
 * design/stitch's sidebar pattern (navy sidebar, active item highlighted);
 * this is not a pixel reproduction.
 */
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { authRequest } from '@/lib/auth'

type NavItem = { label: string; href?: string; active?: boolean }

const props = defineProps<{
    persona: 'candidate' | 'recruiter'
    active: string
    title: string
}>()

const page = usePage<{ props: { auth?: { user?: { name?: string } | null } } }>()
const userName = computed(() => (page.props as any).auth?.user?.name ?? '')

const candidateNav: NavItem[] = [
    { label: 'Dashboard', href: '/dashboard' },
    { label: 'Profil Saya', href: '/candidate/profile/edit' },
    { label: 'CV & Dokumen', href: '/candidate/profile/edit#documents' },
    { label: 'Cari Lowongan', href: '/lowongan' },
    { label: 'Lamaran Saya', href: '/lamaran-saya' },
    { label: 'Jadwal Seleksi', href: '/jadwal-seleksi' },
    { label: 'Lowongan Tersimpan' },
    { label: 'Notifikasi' },
    { label: 'Pengaturan Akun' },
]

const recruiterNav: NavItem[] = [
    { label: 'Dashboard', href: '/dashboard' },
    { label: 'Profil Perusahaan' },
    { label: 'Status Verifikasi' },
    { label: 'Dokumen Legalitas' },
    { label: 'Kemitraan' },
    { label: 'Lowongan' },
    { label: 'Pelamar', href: '/pelamar' },
    { label: 'Jadwal Seleksi', href: '/jadwal-seleksi' },
    { label: 'Outcome Rekrutmen' },
    { label: 'Anggota Perusahaan' },
    { label: 'Notifikasi' },
    { label: 'Pengaturan Akun' },
]

const items = computed(() => props.persona === 'recruiter' ? recruiterNav : candidateNav)

function isActive(item: NavItem): boolean {
    return !!item.href && item.href.split('#')[0].split('?')[0] === '/' + props.active
}

async function logout() {
    await authRequest('/auth/logout', {}, 'POST')
    window.location.assign('/login')
}
</script>

<template>
    <div class="min-h-screen bg-[#f7fafc] text-[#181c1e]">
        <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 md:hidden">
            <span class="font-bold text-[#002045]">Portal Karir Kampus</span>
            <span class="text-sm font-medium text-slate-600">{{ title }}</span>
        </header>

        <aside class="fixed inset-y-0 z-10 hidden w-72 flex-col bg-[#1B2D4F] p-6 text-white md:flex">
            <span class="text-xl font-bold">Portal Karir Kampus</span>
            <p class="mt-1 text-sm text-blue-100">{{ persona === 'recruiter' ? 'Ruang Kerja Recruiter' : 'Portal Karir Terpadu' }}</p>
            <nav class="mt-10 flex-1 space-y-1 overflow-y-auto" aria-label="Navigasi utama">
                <template v-for="item in items" :key="item.label">
                    <Link
                        v-if="item.href"
                        :href="item.href"
                        class="flex rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors"
                        :class="isActive(item) ? 'bg-[#66affe] text-[#004172]' : 'text-white/80 hover:bg-white/10'"
                    >
                        {{ item.label }}
                    </Link>
                    <span
                        v-else
                        class="flex cursor-not-allowed items-center justify-between rounded-lg px-4 py-2.5 text-sm font-medium text-white/40"
                        :title="'Fitur ' + item.label + ' akan hadir pada tahap berikutnya.'"
                    >
                        {{ item.label }}
                        <span class="rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide">Segera hadir</span>
                    </span>
                </template>
            </nav>
            <div class="mt-auto border-t border-white/10 pt-4 text-xs text-white/70">
                <p class="truncate font-medium text-white">{{ userName }}</p>
                <button type="button" class="mt-2 text-white/70 underline-offset-2 hover:underline" @click="logout">Keluar</button>
            </div>
        </aside>

        <main class="mx-auto max-w-6xl p-4 md:ml-72 md:p-10">
            <slot />
        </main>
    </div>
</template>
