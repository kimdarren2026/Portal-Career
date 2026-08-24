<script setup lang="ts">
import { ref } from 'vue'
import AuthMessage from '@/components/auth/AuthMessage.vue'; import AuthShell from '@/components/auth/AuthShell.vue'; import PrimaryButton from '@/components/auth/PrimaryButton.vue'; import { authRequest, errorText } from '@/lib/auth'
const props = defineProps<{ token: string }>(); const loading = ref(false); const message = ref(''); const success = ref(false)
async function submit() { loading.value = true; const { response, payload } = await authRequest('/auth/verify-email', { token: props.token }); loading.value = false; success.value = response.ok; message.value = response.ok ? 'Email berhasil diverifikasi. Akun Anda telah aktif.' : errorText(payload) }
</script>
<template><AuthShell title="Verifikasi email" subtitle="Konfirmasikan alamat email sebelum menggunakan layanan Portal Karir Kampus."><div class="mt-8 rounded-lg bg-slate-50 p-4 text-sm leading-6 text-slate-600">Link ini belum digunakan. Pilih verifikasi untuk mengaktifkan akun Anda.</div><AuthMessage :message="message" :tone="success ? 'success' : 'error'" /><PrimaryButton :loading="loading" @click="submit">Verifikasi email</PrimaryButton><p class="mt-6 text-center text-sm"><a href="/login" class="font-semibold text-[#0b355d] hover:underline">Ke halaman masuk</a></p></AuthShell></template>
