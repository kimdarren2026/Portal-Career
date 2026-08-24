<script setup lang="ts">
import { ref } from 'vue'
import AuthMessage from '@/components/auth/AuthMessage.vue'
import AuthShell from '@/components/auth/AuthShell.vue'
import PrimaryButton from '@/components/auth/PrimaryButton.vue'
import { authRequest, errorText } from '@/lib/auth'

const email = ref('')
const password = ref('')
const remember = ref(false)
const loading = ref(false)
const message = ref('')

async function submit() {
    loading.value = true
    message.value = ''
    const { response, payload } = await authRequest('/auth/login', { email: email.value, password: password.value, remember: remember.value })
    loading.value = false
    if (!response.ok) { message.value = errorText(payload); return }
    window.location.assign('/')
}
</script>

<template>
    <AuthShell title="Masuk ke akun Anda" subtitle="Gunakan alamat email dan password Portal Karir Kampus.">
        <form class="mt-8" @submit.prevent="submit" novalidate>
            <label for="email" class="text-sm font-medium text-slate-800">Email</label>
            <input id="email" v-model="email" type="email" autocomplete="email" required class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-[#0b355d] focus:ring-[#0b355d]" />
            <label for="password" class="mt-5 block text-sm font-medium text-slate-800">Password</label>
            <input id="password" v-model="password" type="password" autocomplete="current-password" required class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-[#0b355d] focus:ring-[#0b355d]" />
            <div class="mt-4 flex items-center justify-between gap-4 text-sm">
                <label class="inline-flex items-center gap-2 text-slate-600"><input v-model="remember" type="checkbox" class="rounded border-slate-300 text-[#0b355d] focus:ring-[#0b355d]" /> Ingat perangkat ini</label>
                <a href="/forgot-password" class="font-semibold text-[#0b355d] hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0b355d]">Lupa password?</a>
            </div>
            <AuthMessage :message="message" tone="error" />
            <PrimaryButton :loading="loading">Masuk</PrimaryButton>
        </form>
        <p class="mt-6 text-center text-sm text-slate-600">Belum memiliki akun? <a href="/register" class="font-semibold text-[#0b355d] hover:underline">Daftar sekarang</a></p>
    </AuthShell>
</template>
