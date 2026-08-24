<script setup lang="ts">
defineProps<{ title: string; subtitle: string }>()
</script>

<template>
    <main class="min-h-screen bg-slate-100 px-4 py-10 text-slate-900 sm:px-6 lg:grid lg:grid-cols-2 lg:p-0">
        <section class="hidden bg-[#092b4c] p-12 text-white lg:flex lg:flex-col lg:justify-between">
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
            <div class="w-full rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-10 lg:max-w-md">
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
