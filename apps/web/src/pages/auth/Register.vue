<script setup lang="ts">
import { computed, ref } from 'vue'
import AuthMessage from '@/components/auth/AuthMessage.vue'
import AuthShell from '@/components/auth/AuthShell.vue'
import PrimaryButton from '@/components/auth/PrimaryButton.vue'
import { authRequest, errorText } from '@/lib/auth'

const props = defineProps<{ accountType?: string }>()
const kind = ref(props.accountType === 'recruiter' ? 'recruiter' : 'candidate')
const name = ref(''); const email = ref(''); const password = ref(''); const confirmation = ref('')
const candidateType = ref('EXTERNAL'); const acceptedTerms = ref(false); const loading = ref(false); const message = ref('')
const endpoint = computed(() => kind.value === 'candidate' ? '/auth/register/candidate' : '/auth/register/recruiter')

async function submit() {
    loading.value = true; message.value = ''
    const body: Record<string, unknown> = { name: name.value, email: email.value, password: password.value, password_confirmation: confirmation.value, accepted_terms: acceptedTerms.value }
    if (kind.value === 'candidate') body.candidate_type = candidateType.value
    const { response, payload } = await authRequest(endpoint.value, body)
    loading.value = false
    message.value = response.ok ? 'Pendaftaran diterima. Periksa email Anda untuk melanjutkan verifikasi.' : errorText(payload)
}
</script>

<template>
    <AuthShell title="Buat akun" subtitle="Mulai perjalanan karir Anda dengan identitas yang aman dan terverifikasi.">
        <div class="mt-7 grid grid-cols-2 rounded-lg bg-slate-100 p-1 text-sm font-semibold">
            <button type="button" class="rounded-md px-3 py-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0b355d]" :class="kind === 'candidate' ? 'bg-white text-[#0b355d] shadow-sm' : 'text-slate-600'" @click="kind = 'candidate'">Kandidat</button>
            <button type="button" class="rounded-md px-3 py-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0b355d]" :class="kind === 'recruiter' ? 'bg-white text-[#0b355d] shadow-sm' : 'text-slate-600'" @click="kind = 'recruiter'">Recruiter</button>
        </div>
        <form class="mt-6" @submit.prevent="submit" novalidate>
            <label for="name" class="text-sm font-medium">Nama lengkap</label><input id="name" v-model="name" autocomplete="name" required class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-[#0b355d] focus:ring-[#0b355d]" />
            <label for="register-email" class="mt-4 block text-sm font-medium">Email</label><input id="register-email" v-model="email" type="email" autocomplete="email" required class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-[#0b355d] focus:ring-[#0b355d]" />
            <template v-if="kind === 'candidate'"><label for="candidate-type" class="mt-4 block text-sm font-medium">Kategori kandidat</label><select id="candidate-type" v-model="candidateType" class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-[#0b355d] focus:ring-[#0b355d]"><option value="EXTERNAL">Kandidat eksternal</option><option value="FINAL_YEAR_STUDENT">Mahasiswa tingkat akhir</option><option value="ALUMNI">Alumni</option></select></template>
            <label for="new-password" class="mt-4 block text-sm font-medium">Password</label><input id="new-password" v-model="password" type="password" autocomplete="new-password" aria-describedby="password-help" required class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-[#0b355d] focus:ring-[#0b355d]" /><p id="password-help" class="mt-1 text-xs text-slate-500">Minimal 8 karakter dengan huruf besar, huruf kecil, dan angka.</p>
            <label for="password-confirmation" class="mt-4 block text-sm font-medium">Konfirmasi password</label><input id="password-confirmation" v-model="confirmation" type="password" autocomplete="new-password" required class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-[#0b355d] focus:ring-[#0b355d]" />
            <label class="mt-5 flex items-start gap-3 text-sm text-slate-600"><input v-model="acceptedTerms" type="checkbox" required class="mt-1 rounded border-slate-300 text-[#0b355d] focus:ring-[#0b355d]" /> <span>Saya menyetujui syarat layanan dan kebijakan privasi.</span></label>
            <AuthMessage :message="message" :tone="message.includes('diterima') ? 'success' : 'error'" />
            <PrimaryButton :loading="loading">Daftar sebagai {{ kind === 'candidate' ? 'kandidat' : 'recruiter' }}</PrimaryButton>
        </form>
        <p class="mt-6 text-center text-sm text-slate-600">Sudah punya akun? <a href="/login" class="font-semibold text-[#0b355d] hover:underline">Masuk</a></p>
    </AuthShell>
</template>
