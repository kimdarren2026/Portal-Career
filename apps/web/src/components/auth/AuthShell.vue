<script setup lang="ts">
defineProps<{ title: string; subtitle: string }>()
</script>

<template>
    <main class="min-h-screen bg-[radial-gradient(circle_at_top_left,_#dbeafe,_#f8fafc_48%,_#e2e8f0)] px-4 py-10 text-slate-900 sm:px-6 lg:grid lg:grid-cols-2 lg:p-0">
        <section class="hidden bg-[radial-gradient(circle_at_top_right,_#176bb0,_transparent_42%),linear-gradient(145deg,_#061d3d,_#0b3769)] p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <a href="/" class="inline-flex items-center gap-3 text-lg font-bold tracking-tight focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#f6b928] text-[#092b4c]" aria-hidden="true">PK</span>
                Portal Karir Kampus
            </a>
            <div class="max-w-md">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-[#f6b928]">Masa depan profesional</p>
                <p class="mt-5 text-4xl font-semibold leading-tight">Temukan kesempatan yang selaras dengan langkah berikutnya.</p>
                <p class="mt-5 text-base leading-7 text-slate-200">Satu ruang aman untuk kandidat, recruiter, dan layanan karir kampus.</p>
            </div>
            <p class="text-sm text-slate-300">© {{ new Date().getFullYear() }} Portal Karir Kampus</p>
        </section>
        <section class="mx-auto flex w-full max-w-xl items-center py-6 lg:max-w-none lg:px-16 xl:px-24">
            <div class="w-full rounded-3xl bg-white/95 p-6 shadow-[0_24px_70px_rgba(15,23,42,0.14)] ring-1 ring-slate-200/80 backdrop-blur sm:p-10 lg:max-w-md">
                <a href="/" class="mb-10 inline-flex items-center gap-2 font-bold text-[#092b4c] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#092b4c] lg:hidden">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-[#f6b928] text-xs" aria-hidden="true">PK</span>
                    Portal Karir Kampus
                </a>
                <h1 class="text-3xl font-bold tracking-tight text-[#092b4c]">{{ title }}</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ subtitle }}</p>
                <slot />
            </div>
        </section>
    </main>
</template>
