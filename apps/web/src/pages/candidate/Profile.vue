<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'
import CollectionEditor from '@/components/candidate/CollectionEditor.vue'
import AppShell from '@/layouts/AppShell.vue'
import { authFormRequest, authRequest, errorText } from '@/lib/auth'

type Item = Record<string, unknown>
type Profile = Record<string, unknown> & { current_candidate_type?: string; profile_completed_at?: string | null; collections?: { counts?: Record<string, number> } }
type Verification = { id: number; verification_type: string; status: string; verified_at?: string | null }
type Document = { id: number; display_name: string; document_type: string; mime_type: string; size: number; uploaded_at?: string | null; archived_at?: string | null }

const props = defineProps<{ profile: Profile; collections: Record<string, Item[]>; verifications: Verification[]; documents: { items: Document[]; pagination: { page: number; total: number } } }>()
const form = reactive({
    headline: String(props.profile.headline ?? ''), phone: String(props.profile.phone ?? ''), summary: String(props.profile.summary ?? ''),
    province: String(props.profile.province ?? ''), city: String(props.profile.city ?? ''),
    preferred_employment_type: String(props.profile.preferred_employment_type ?? ''), preferred_workplace_mode: String(props.profile.preferred_workplace_mode ?? ''),
    preferred_location_note: String(props.profile.preferred_location_note ?? ''), open_to_opportunities: props.profile.open_to_opportunities ?? null,
})
const status = ref(''); const saving = ref(false)
const collections = reactive<Record<string, Item[]>>({ ...props.collections })
const documents = ref<Document[]>(props.documents.items)
const uploadForm = reactive({ document_type: '', display_name: '' })
const uploadFile = ref<File | null>(null)
const uploadInput = ref<HTMLInputElement | null>(null)
const uploading = ref(false)
const uploadStatus = ref('')
const uploadErrors = ref<Record<string, string[]>>({})

const candidateType = computed(() => ({ EXTERNAL: 'Kandidat eksternal', FINAL_YEAR_STUDENT: 'Mahasiswa tingkat akhir', ALUMNI: 'Alumni' }[String(props.profile.current_candidate_type)] ?? props.profile.current_candidate_type))
const detail = (key: string) => (collections[key] ?? [])

async function saveProfile() {
    saving.value = true; status.value = ''
    const payload = Object.fromEntries(Object.entries(form).map(([key, value]) => [key, value === '' ? null : value]))
    const result = await authRequest('/candidate/profile', payload, 'PATCH')
    saving.value = false
    status.value = result.response.ok ? 'Profil dasar disimpan.' : errorText(result.payload)
}
async function archiveDocument(document: Document) {
    const { response, payload } = await authRequest(`/candidate/documents/${document.id}`, {}, 'DELETE')
    if (response.ok) documents.value = documents.value.map((item) => item.id === document.id ? { ...item, archived_at: new Date().toISOString() } : item)
    else status.value = errorText(payload)
}
function selectDocument(event: Event) {
    uploadFile.value = (event.target as HTMLInputElement).files?.[0] ?? null
    uploadErrors.value = { ...uploadErrors.value, file: [] }
}
async function uploadDocument() {
    uploadStatus.value = ''
    uploadErrors.value = {}
    if (!uploadFile.value) {
        uploadErrors.value = { file: ['Pilih berkas PDF terlebih dahulu.'] }
        return
    }

    uploading.value = true
    const body = new FormData()
    body.append('file', uploadFile.value)
    body.append('document_type', uploadForm.document_type)
    if (uploadForm.display_name !== '') body.append('display_name', uploadForm.display_name)

    const result = await authFormRequest('/candidate/documents', body)
    uploading.value = false
    if (result.response.ok) {
        documents.value = [result.payload.data as Document, ...documents.value]
        uploadForm.document_type = ''
        uploadForm.display_name = ''
        uploadFile.value = null
        if (uploadInput.value) uploadInput.value.value = ''
        uploadStatus.value = 'Dokumen berhasil diunggah.'
        return
    }

    uploadErrors.value = result.payload.error?.details?.fields ?? {}
    uploadStatus.value = errorText(result.payload)
}
</script>

<template>
    <Head title="Profil Saya" />
    <AppShell persona="candidate" active="candidate/profile/edit" title="Profil Saya">
            <div class="mb-8 flex flex-wrap items-start justify-between gap-4"><div><p class="text-sm font-semibold text-[#0061a5]">Kandidat · {{ candidateType }}</p><h1 class="mt-1 text-3xl font-bold tracking-tight text-[#002045]">Profil Saya</h1><p class="mt-2 max-w-2xl text-slate-600">Kelola profil profesional dan riwayat Anda. Status kelengkapan tidak dihitung otomatis.</p></div><span class="rounded-full border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700">{{ profile.profile_completed_at ? 'Profil memiliki catatan penyelesaian' : 'Profil dapat dilengkapi bertahap' }}</span></div>
            <p v-if="status" class="mb-5 rounded-lg border p-4 text-sm" :class="status === 'Profil dasar disimpan.' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-[#93000a]'" role="status">{{ status }}</p>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-lg font-semibold text-[#002045]">Ringkasan profil</h2><div class="mt-4 grid gap-4 md:grid-cols-2"><label class="text-sm font-medium text-slate-700">Headline<input v-model="form.headline" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label><label class="text-sm font-medium text-slate-700">Nomor telepon<input v-model="form.phone" inputmode="tel" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label><label class="text-sm font-medium text-slate-700">Provinsi (fallback teks)<input v-model="form.province" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label><label class="text-sm font-medium text-slate-700">Kota (fallback teks)<input v-model="form.city" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label><label class="text-sm font-medium text-slate-700">Preferensi jenis kerja<input v-model="form.preferred_employment_type" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label><label class="text-sm font-medium text-slate-700">Preferensi cara kerja<input v-model="form.preferred_workplace_mode" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label><label class="text-sm font-medium text-slate-700">Catatan lokasi<input v-model="form.preferred_location_note" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label><label class="text-sm font-medium text-slate-700">Terbuka untuk peluang<select v-model="form.open_to_opportunities" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]"><option :value="null">Belum ditentukan</option><option :value="true">Ya</option><option :value="false">Tidak</option></select></label><label class="md:col-span-2 text-sm font-medium text-slate-700">Ringkasan profesional<textarea v-model="form.summary" rows="4" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" /></label></div><button type="button" :disabled="saving" class="mt-5 rounded-lg bg-[#0061a5] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#004172] disabled:opacity-60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0061a5]" @click="saveProfile">{{ saving ? 'Menyimpan…' : 'Simpan profil dasar' }}</button></section>
            <div class="mt-6 grid gap-6"><CollectionEditor title="Pendidikan" endpoint="/candidate/educations" :items="detail('educations')" :fields="[{ key: 'institution_name', label: 'Institusi', required: true }, { key: 'education_level', label: 'Jenjang', required: true }, { key: 'study_program_name', label: 'Program studi' }, { key: 'start_date', label: 'Mulai', type: 'date' }, { key: 'graduation_date', label: 'Lulus', type: 'date' }, { key: 'graduation_year', label: 'Tahun lulus', type: 'number' }, { key: 'score_summary', label: 'Ringkasan nilai' }]" @saved="collections.educations = $event" /><CollectionEditor title="Pengalaman kerja" endpoint="/candidate/work-experiences" :items="detail('work-experiences')" :fields="[{ key: 'employer_name', label: 'Pemberi kerja', required: true }, { key: 'position_title', label: 'Posisi', required: true }, { key: 'employment_type', label: 'Jenis kerja' }, { key: 'start_date', label: 'Mulai', type: 'date' }, { key: 'end_date', label: 'Selesai', type: 'date' }, { key: 'is_current', label: 'Masih bekerja', type: 'boolean', required: true }]" @saved="collections['work-experiences'] = $event" /><CollectionEditor title="Organisasi" endpoint="/candidate/organizations" :items="detail('organizations')" :fields="[{ key: 'organization_name', label: 'Organisasi', required: true }, { key: 'role_title', label: 'Peran', required: true }, { key: 'organization_type', label: 'Jenis organisasi' }, { key: 'start_date', label: 'Mulai', type: 'date' }, { key: 'end_date', label: 'Selesai', type: 'date' }, { key: 'is_current', label: 'Masih aktif', type: 'boolean', required: true }]" @saved="collections.organizations = $event" /><CollectionEditor title="Sertifikasi" endpoint="/candidate/certifications" :items="detail('certifications')" :fields="[{ key: 'certification_name', label: 'Sertifikasi', required: true }, { key: 'issuer_name', label: 'Penerbit', required: true }, { key: 'credential_identifier', label: 'Nomor kredensial' }, { key: 'issued_at', label: 'Terbit', type: 'date' }, { key: 'expires_at', label: 'Berakhir', type: 'date' }, { key: 'credential_url', label: 'URL kredensial' }, { key: 'document_id', label: 'ID dokumen pribadi', type: 'number' }]" @saved="collections.certifications = $event" /><CollectionEditor title="Tautan profesional" endpoint="/candidate/links" :items="detail('links')" :fields="[{ key: 'link_type', label: 'Jenis tautan', type: 'linkType', required: true }, { key: 'label', label: 'Label' }, { key: 'url', label: 'URL aman (http/https)', required: true }, { key: 'sort_order', label: 'Urutan', type: 'number', required: true }]" @saved="collections.links = $event" /><CollectionEditor title="Keterampilan" endpoint="/candidate/skills" :items="detail('skills')" help="Masukkan ID keterampilan dari master data aktif yang telah disediakan organisasi." :fields="[{ key: 'skill_id', label: 'ID keterampilan', type: 'number', required: true }, { key: 'proficiency_level', label: 'Tingkat kemahiran' }]" @saved="collections.skills = $event" /></div>
            <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-lg font-semibold text-[#002045]">Status verifikasi</h2><p v-if="!verifications.length" class="mt-3 text-sm text-slate-600">Belum ada status verifikasi. Pengajuan verifikasi belum tersedia hingga keputusan bisnis ditetapkan.</p><ul v-else class="mt-3 space-y-2"><li v-for="verification in verifications" :key="verification.id" class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 p-3 text-sm"><span class="font-medium text-slate-800">{{ verification.verification_type }}</span><span class="rounded-full border border-slate-300 px-2.5 py-1 font-medium text-slate-700">{{ verification.status }}</span></li></ul></section>
            <section id="documents" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-lg font-semibold text-[#002045]">Dokumen pribadi</h2><p class="mt-1 text-sm text-slate-600">Unggah PDF pribadi hingga 10 MiB. Jenis dokumen dapat Anda tulis sesuai kebutuhan.</p><form class="mt-4 grid gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-2" @submit.prevent="uploadDocument"><label class="text-sm font-medium text-slate-700">Berkas PDF<input ref="uploadInput" type="file" accept="application/pdf,.pdf" class="mt-1 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-[#dceeff] file:px-3 file:py-2 file:font-semibold file:text-[#004172] hover:file:bg-[#c6e3ff]" @change="selectDocument" /><span v-if="uploadErrors.file?.length" class="mt-1 block text-sm text-[#93000a]">{{ uploadErrors.file[0] }}</span></label><label class="text-sm font-medium text-slate-700">Jenis dokumen<input v-model="uploadForm.document_type" maxlength="64" required class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" placeholder="Contoh: CV, portofolio, sertifikat" /><span v-if="uploadErrors.document_type?.length" class="mt-1 block text-sm text-[#93000a]">{{ uploadErrors.document_type[0] }}</span></label><label class="text-sm font-medium text-slate-700 md:col-span-2">Nama tampilan (opsional)<input v-model="uploadForm.display_name" maxlength="255" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" placeholder="Nama yang ingin ditampilkan" /><span v-if="uploadErrors.display_name?.length" class="mt-1 block text-sm text-[#93000a]">{{ uploadErrors.display_name[0] }}</span></label><div class="flex flex-wrap items-center gap-3 md:col-span-2"><button type="submit" :disabled="uploading" class="rounded-lg bg-[#0061a5] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#004172] disabled:opacity-60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0061a5]">{{ uploading ? 'Mengunggah…' : 'Unggah dokumen' }}</button><p v-if="uploadStatus" class="text-sm" :class="uploadStatus === 'Dokumen berhasil diunggah.' ? 'text-green-800' : 'text-[#93000a]'" role="status">{{ uploadStatus }}</p></div></form><p v-if="!documents.length" class="mt-4 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">Belum ada dokumen tersimpan.</p><ul v-else class="mt-4 space-y-3"><li v-for="document in documents" :key="document.id" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-4"><div><p class="font-medium text-slate-800">{{ document.display_name }}</p><p class="text-sm text-slate-600">{{ document.document_type }} · {{ document.mime_type }} · {{ document.size }} byte</p></div><button type="button" :disabled="Boolean(document.archived_at)" class="rounded-lg border border-[#93000a] px-3 py-2 text-sm font-semibold text-[#93000a] hover:bg-red-50 disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#93000a]" @click="archiveDocument(document)">{{ document.archived_at ? 'Diarsipkan' : 'Arsipkan' }}</button></li></ul></section>
    </AppShell>
</template>
