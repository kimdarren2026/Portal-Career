<script setup lang="ts">
/**
 * Shared authenticated nav shell. Preserves the full canonical
 * candidate/recruiter menu — items this slice does not yet implement render
 * as a disabled "Segera hadir" entry rather than a dead link, per the
 * no-dead-link requirement. Visual structure follows design/stitch's sidebar
 * pattern (navy sidebar, active item highlighted); not a pixel reproduction.
 *
 * FE-2: below the `md` breakpoint the fixed sidebar is hidden and the same
 * menu is reached through an accessible slide-over drawer. The drawer renders
 * from the exact same `items`/`isActive` source as the desktop sidebar — no
 * item is added or removed, deferred items stay disabled, and no route or
 * authorization is changed.
 */
import { Link, usePage } from '@inertiajs/vue3'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { authRequest } from '@/lib/auth'

type NavItem = { label: string; href?: string; active?: boolean }

const props = defineProps<{
    persona: 'candidate' | 'recruiter' | 'career-center' | 'admin-kepegawaian'
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
    { label: 'Profil Perusahaan', href: '/profil-perusahaan' },
    { label: 'Status Verifikasi', href: '/status-verifikasi' },
    { label: 'Dokumen Legalitas' },
    { label: 'Kemitraan' },
    { label: 'Lowongan', href: '/kelola-lowongan' },
    { label: 'Pelamar', href: '/pelamar' },
    { label: 'Jadwal Seleksi', href: '/jadwal-seleksi' },
    { label: 'Outcome Rekrutmen', href: '/outcome-rekrutmen' },
    { label: 'Anggota Perusahaan', href: '/anggota-perusahaan' },
    { label: 'Notifikasi', href: '/notifikasi' },
    { label: 'Pengaturan Akun', href: '/pengaturan-akun' },
]

// Career Center — exactly 10 canonical items. Frontend Vertical Slice v4
// activates "Verifikasi Perusahaan" and "Moderasi Lowongan" only; the rest
// stay deferred ("Segera hadir") per the no-dead-link rule.
const careerCenterNav: NavItem[] = [
    { label: 'Dashboard' },
    { label: 'Verifikasi Perusahaan', href: '/verifikasi-perusahaan' },
    { label: 'Moderasi Lowongan', href: '/moderasi-lowongan' },
    { label: 'Data Perusahaan' },
    { label: 'Kemitraan' },
    { label: 'Alumni & Outcome' },
    { label: 'Laporan' },
    { label: 'Notifikasi' },
    { label: 'Template Email' },
    { label: 'Pengaturan Moderasi' },
]

// Admin Kepegawaian — exactly 10 canonical items (Campus Recruitment
// Frontend v8). "Laporan" stays deferred ("Segera hadir") — FR-REP-003 /
// GET /reports has no runtime.
const adminKepegawaianNav: NavItem[] = [
    { label: 'Dashboard', href: '/kepegawaian/dashboard' },
    { label: 'Lowongan Kampus', href: '/kepegawaian/lowongan-kampus' },
    { label: 'Pelamar', href: '/kepegawaian/pelamar' },
    { label: 'Jadwal Seleksi', href: '/kepegawaian/jadwal-seleksi' },
    { label: 'Penilaian', href: '/kepegawaian/penilaian' },
    { label: 'Offering', href: '/kepegawaian/offering' },
    { label: 'Outcome Rekrutmen', href: '/kepegawaian/outcome-rekrutmen' },
    { label: 'Laporan' },
    { label: 'Notifikasi', href: '/kepegawaian/notifikasi' },
    { label: 'Pengaturan', href: '/kepegawaian/pengaturan' },
]

const items = computed(() => {
    if (props.persona === 'recruiter') return recruiterNav
    if (props.persona === 'career-center') return careerCenterNav
    if (props.persona === 'admin-kepegawaian') return adminKepegawaianNav
    return candidateNav
})

const personaSubtitle = computed(() => {
    if (props.persona === 'recruiter') return 'Ruang Kerja Recruiter'
    if (props.persona === 'career-center') return 'Back-office Career Center'
    if (props.persona === 'admin-kepegawaian') return 'Back Office Kepegawaian'
    return 'Portal Karir Terpadu'
})

function isActive(item: NavItem): boolean {
    return !!item.href && item.href.split('#')[0].split('?')[0] === '/' + props.active
}

async function logout() {
    await authRequest('/auth/logout', {}, 'POST')
    window.location.assign('/login')
}

/* ---- FE-2: mobile navigation drawer ---- */
const mobileNavOpen = ref(false)
const menuTrigger = ref<HTMLButtonElement | null>(null)
const drawer = ref<HTMLElement | null>(null)

function openMobileNav(): void {
    mobileNavOpen.value = true
}

function closeMobileNav(): void {
    if (!mobileNavOpen.value) return
    mobileNavOpen.value = false
    nextTick(() => menuTrigger.value?.focus())
}

function onDrawerKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        closeMobileNav()
        return
    }
    if (event.key !== 'Tab' || !drawer.value) return
    const focusable = Array.from(
        drawer.value.querySelectorAll<HTMLElement>('a[href], button:not([disabled])'),
    ).filter((el) => el.offsetParent !== null)
    if (focusable.length === 0) return
    const first = focusable[0]
    const last = focusable[focusable.length - 1]
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first.focus()
    }
}

watch(mobileNavOpen, (open) => {
    if (typeof document === 'undefined') return
    document.body.style.overflow = open ? 'hidden' : ''
    if (open) {
        nextTick(() => drawer.value?.querySelector<HTMLElement>('button, a[href]')?.focus())
    }
})

let desktopQuery: MediaQueryList | null = null
function handleBreakpoint(event: MediaQueryListEvent): void {
    if (event.matches) closeMobileNav()
}

onMounted(() => {
    if (typeof window === 'undefined') return
    desktopQuery = window.matchMedia('(min-width: 768px)')
    desktopQuery.addEventListener('change', handleBreakpoint)
})

onBeforeUnmount(() => {
    desktopQuery?.removeEventListener('change', handleBreakpoint)
    if (typeof document !== 'undefined') document.body.style.overflow = ''
})
</script>

<template>
    <div class="min-h-screen bg-[#f7fafc] text-[#181c1e]">
        <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 md:hidden">
            <div class="flex items-center gap-2">
                <button
                    ref="menuTrigger"
                    type="button"
                    class="-ml-2 inline-flex h-10 w-10 items-center justify-center rounded-lg text-[#002045] hover:bg-slate-100"
                    :aria-expanded="mobileNavOpen"
                    aria-controls="mobile-nav-drawer"
                    aria-label="Buka menu navigasi"
                    @click="openMobileNav"
                >
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    </svg>
                </button>
                <span class="font-bold text-[#002045]">Portal Karir Kampus</span>
            </div>
            <span class="text-sm font-medium text-slate-600">{{ title }}</span>
        </header>

        <Transition
            enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
            enter-from-class="opacity-0 [&_[data-drawer-panel]]:-translate-x-full"
            leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
            leave-to-class="opacity-0 [&_[data-drawer-panel]]:-translate-x-full"
        >
            <div v-if="mobileNavOpen" class="fixed inset-0 z-40 md:hidden" @keydown="onDrawerKeydown">
                <div class="absolute inset-0 bg-black/40" aria-hidden="true" @click="closeMobileNav" />
                <div
                    id="mobile-nav-drawer"
                    ref="drawer"
                    data-drawer-panel
                    role="dialog"
                    aria-modal="true"
                    aria-label="Menu navigasi"
                    class="absolute inset-y-0 left-0 flex w-72 max-w-[85vw] flex-col bg-[#1B2D4F] p-6 text-white shadow-xl transition-transform duration-200 ease-out motion-reduce:transition-none"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <span class="text-xl font-bold">Portal Karir Kampus</span>
                            <p class="mt-1 text-sm text-blue-100">{{ personaSubtitle }}</p>
                        </div>
                        <button
                            type="button"
                            class="-mr-2 -mt-1 inline-flex h-10 w-10 items-center justify-center rounded-lg text-white/80 hover:bg-white/10"
                            aria-label="Tutup menu navigasi"
                            @click="closeMobileNav"
                        >
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>
                    <nav class="mt-8 flex-1 space-y-1 overflow-y-auto" aria-label="Navigasi utama">
                        <template v-for="item in items" :key="item.label">
                            <Link
                                v-if="item.href"
                                :href="item.href"
                                class="flex rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors"
                                :class="isActive(item) ? 'bg-[#66affe] text-[#004172]' : 'text-white/80 hover:bg-white/10'"
                                :aria-current="isActive(item) ? 'page' : undefined"
                                @click="closeMobileNav"
                            >
                                {{ item.label }}
                            </Link>
                            <span
                                v-else
                                class="flex cursor-not-allowed items-center justify-between rounded-lg px-4 py-2.5 text-sm font-medium text-white/40"
                                aria-disabled="true"
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
                </div>
            </div>
        </Transition>

        <aside class="fixed inset-y-0 z-10 hidden w-72 flex-col bg-[#1B2D4F] p-6 text-white md:flex">
            <span class="text-xl font-bold">Portal Karir Kampus</span>
            <p class="mt-1 text-sm text-blue-100">{{ personaSubtitle }}</p>
            <nav class="mt-10 flex-1 space-y-1 overflow-y-auto" aria-label="Navigasi utama">
                <template v-for="item in items" :key="item.label">
                    <Link
                        v-if="item.href"
                        :href="item.href"
                        class="flex rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors"
                        :class="isActive(item) ? 'bg-[#66affe] text-[#004172]' : 'text-white/80 hover:bg-white/10'"
                        :aria-current="isActive(item) ? 'page' : undefined"
                    >
                        {{ item.label }}
                    </Link>
                    <span
                        v-else
                        class="flex cursor-not-allowed items-center justify-between rounded-lg px-4 py-2.5 text-sm font-medium text-white/40"
                        aria-disabled="true"
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
