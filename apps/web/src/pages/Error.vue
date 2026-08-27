<script setup lang="ts">
/**
 * Shared styled error page for browser/Inertia GET page navigation only.
 * Mutation/action JSON endpoints never render this — they keep returning
 * their existing `ContractResponse` envelope untouched (see
 * `bootstrap/app.php`, gated on `$request->expectsJson()`). Never displays
 * an exception class name, stack trace, SQLSTATE, or a resource identifier —
 * the same enumeration-safe wording the JSON contract already uses.
 */
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps<{ status: number }>()

const copy = computed(() => {
    const map: Record<number, { title: string; message: string }> = {
        403: { title: 'Akses tidak diizinkan', message: 'Anda tidak berhak mengakses halaman ini.' },
        404: { title: 'Halaman tidak ditemukan', message: 'Halaman yang Anda cari tidak tersedia atau sudah tidak dapat diakses.' },
        419: { title: 'Sesi telah berakhir', message: 'Silakan muat ulang halaman dan coba kembali.' },
        500: { title: 'Terjadi kesalahan', message: 'Terjadi kesalahan pada server. Silakan coba beberapa saat lagi.' },
        503: { title: 'Layanan sedang tidak tersedia', message: 'Portal sedang dalam pemeliharaan. Silakan coba beberapa saat lagi.' },
    }
    return map[props.status] ?? { title: 'Terjadi kesalahan', message: 'Permintaan tidak dapat diproses. Silakan coba kembali.' }
})
</script>

<template>
    <Head :title="copy.title" />
    <main class="flex min-h-screen items-center justify-center bg-[#f7fafc] px-4">
        <div class="max-w-md text-center">
            <p class="text-sm font-semibold text-[#0061a5]">Error {{ status }}</p>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#002045]">{{ copy.title }}</h1>
            <p class="mt-3 text-slate-600">{{ copy.message }}</p>
            <Link href="/dashboard" class="mt-6 inline-block rounded-lg bg-[#0061a5] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#004172]">
                Kembali ke Dashboard
            </Link>
        </div>
    </main>
</template>
