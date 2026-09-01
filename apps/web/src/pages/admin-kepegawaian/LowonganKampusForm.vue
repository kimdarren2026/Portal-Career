<script setup lang="ts">
/** Admin Kepegawaian — campus vacancy create / edit + lifecycle (v8).
 *  Create posts to POST /hr/vacancies; edit to PATCH /vacancies/{id};
 *  lifecycle to POST /hr/vacancies/{id}/{action}. */
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { authRequest, errorText } from '@/lib/auth'
import { statusLabel, vacancyStatusBadgeClass, vacancyStatusLabel } from '@/lib/labels'

interface RefRow { id: number; code?: string | null; name: string }
interface Vacancy {
    id: number; title: string; description: string; responsibilities: string | null
    employment_type: string; workplace_mode: string | null; location: string | null
    openings_count: number; target_audience: string; minimum_education: string | null
    experience_requirement: string | null; open_at: string | null; close_at: string | null
    current_status: string; organizational_unit_id: number | null
}

const props = defineProps<{
    mode: 'create' | 'edit'
    vacancy: Vacancy | null
    reference_data: { organizational_units: RefRow[] }
    editable: boolean
    lifecycle: string[]
}>()

const employmentOptions = [
    ['FULL_TIME', 'Penuh Waktu'], ['PART_TIME', 'Paruh Waktu'], ['CONTRACT', 'Kontrak'],
    ['FREELANCE', 'Freelance'], ['TEMPORARY', 'Sementara'],
]
const workplaceOptions = [['ONSITE', 'On-site'], ['HYBRID', 'Hybrid'], ['REMOTE', 'Remote']]
const audienceOptions = [
    ['PUBLIC', 'Publik'], ['ALUMNI_ONLY', 'Alumni'],
    ['FINAL_YEAR_AND_ALUMNI', 'Mahasiswa Tingkat Akhir & Alumni'], ['INTERNAL', 'Internal'],
]
const lifecycleLabels: Record<string, string> = {
    publish: 'Publikasikan', schedule: 'Jadwalkan', close: 'Tutup', suspend: 'Tangguhkan', restore: 'Pulihkan',
}

const editing = ref(props.mode === 'create')
const saving = ref(false)
const message = ref('')
const fieldErrors = ref<Record<string, string[]>>({})
const v = props.vacancy

const form = reactive({
    title: v?.title ?? '',
    description: v?.description ?? '',
    responsibilities: v?.responsibilities ?? '',
    employment_type: v?.employment_type ?? 'FULL_TIME',
    workplace_mode: v?.workplace_mode ?? 'ONSITE',
    location: v?.location ?? '',
    openings_count: v?.openings_count ?? 1,
    target_audience: v?.target_audience ?? 'PUBLIC',
    minimum_education: v?.minimum_education ?? '',
    experience_requirement: v?.experience_requirement ?? '',
    open_at: v?.open_at?.slice(0, 16) ?? '',
    close_at: v?.close_at?.slice(0, 16) ?? '',
    organizational_unit_id: v?.organizational_unit_id ?? (null as number | null),
})

const noUnits = computed(() => props.reference_data.organizational_units.length === 0)

function payload(): Record<string, unknown> {
    const p: Record<string, unknown> = {
        title: form.title,
        description: form.description,
        responsibilities: form.responsibilities || null,
        employment_type: form.employment_type,
        workplace_mode: form.workplace_mode,
        location: form.location || null,
        openings_count: Number(form.openings_count),
        target_audience: form.target_audience,
        minimum_education: form.minimum_education || null,
        experience_requirement: form.experience_requirement || null,
        application_method: 'IN_PORTAL',
        open_at: form.open_at ? new Date(form.open_at).toISOString() : null,
        close_at: form.close_at ? new Date(form.close_at).toISOString() : null,
    }
    if (props.mode === 'create') p.organizational_unit_id = form.organizational_unit_id
    return p
}

async function save() {
    saving.value = true
    message.value = ''
    fieldErrors.value = {}
    const isCreate = props.mode === 'create'
    const { response, payload: body } = await authRequest(
        isCreate ? '/hr/vacancies' : `/vacancies/${v!.id}`,
        payload(),
        isCreate ? 'POST' : 'PATCH',
    )
    saving.value = false
    if (!response.ok) {
        fieldErrors.value = body.error?.details?.fields ?? {}
        message.value = errorText(body)
        return
    }
    if (isCreate) {
        router.visit(`/kepegawaian/lowongan-kampus/${(body.data as { id: number }).id}`)
    } else {
        router.reload()
    }
}

const lifecycleBusy = ref('')
async function runLifecycle(action: string) {
    if (lifecycleBusy.value) return
    lifecycleBusy.value = action
    message.value = ''
    const { response, payload: body } = await authRequest(`/hr/vacancies/${v!.id}/${action}`, {}, 'POST')
    lifecycleBusy.value = ''
    if (!response.ok) {
        message.value = errorText(body)
        return
    }
    router.reload()
}
</script>

<template>
    <Head :title="mode === 'create' ? 'Lowongan Kampus Baru' : 'Detail Lowongan Kampus'" />
    <AppShell persona="admin-kepegawaian" active="kepegawaian/lowongan-kampus" title="Lowongan Kampus">
        <Link href="/kepegawaian/lowongan-kampus" class="text-sm font-semibold text-[#0061a5] hover:underline">← Kembali ke daftar</Link>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-[#002045]">
            {{ mode === 'create' ? 'Lowongan Kampus Baru' : (vacancy?.title || 'Lowongan Kampus') }}
        </h1>

        <div v-if="vacancy" class="mt-3 flex flex-wrap items-center gap-2">
            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="vacancyStatusBadgeClass[vacancy.current_status] ?? 'bg-slate-100 text-slate-700'">
                {{ statusLabel(vacancyStatusLabel, vacancy.current_status) }}
            </span>
            <button v-for="a in lifecycle" :key="a" type="button"
                class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:border-[#0061a5]"
                @click="runLifecycle(a)">{{ lifecycleLabels[a] ?? a }}</button>
        </div>

        <p v-if="message" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-[#93000a]" role="alert">{{ message }}</p>
        <p v-if="mode === 'create' && noUnits" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Belum ada unit organisasi (fakultas/bagian) yang aktif pada master data. Hubungi administrator sistem untuk menambahkannya sebelum membuat lowongan kampus.
        </p>

        <form class="mt-6 space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="save">
            <fieldset :disabled="!editing || saving" class="space-y-5">
                <label v-if="mode === 'create'" class="block text-sm font-medium text-slate-700">Unit / Fakultas / Bagian <span class="text-red-500">*</span>
                    <select v-model="form.organizational_unit_id" required class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]">
                        <option :value="null">Pilih unit organisasi</option>
                        <option v-for="u in reference_data.organizational_units" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                    <span v-if="fieldErrors.organizational_unit_id" class="mt-1 block text-xs text-red-600">{{ fieldErrors.organizational_unit_id[0] }}</span>
                </label>

                <label class="block text-sm font-medium text-slate-700">Judul Posisi <span class="text-red-500">*</span>
                    <input v-model="form.title" required class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" />
                    <span v-if="fieldErrors.title" class="mt-1 block text-xs text-red-600">{{ fieldErrors.title[0] }}</span>
                </label>

                <label class="block text-sm font-medium text-slate-700">Deskripsi <span class="text-red-500">*</span>
                    <textarea v-model="form.description" required rows="4" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" />
                </label>

                <label class="block text-sm font-medium text-slate-700">Tanggung Jawab / Kualifikasi
                    <textarea v-model="form.responsibilities" rows="3" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" />
                </label>

                <div class="grid gap-5 md:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-700">Status Kepegawaian
                        <select v-model="form.employment_type" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]">
                            <option v-for="[val, lbl] in employmentOptions" :key="val" :value="val">{{ lbl }}</option>
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Sistem Kerja
                        <select v-model="form.workplace_mode" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]">
                            <option v-for="[val, lbl] in workplaceOptions" :key="val" :value="val">{{ lbl }}</option>
                        </select>
                    </label>
                </div>

                <div class="grid gap-5 md:grid-cols-3">
                    <label class="block text-sm font-medium text-slate-700">Lokasi
                        <input v-model="form.location" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" />
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Jumlah Kebutuhan <span class="text-red-500">*</span>
                        <input v-model.number="form.openings_count" type="number" min="1" required class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" />
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Target Kandidat
                        <select v-model="form.target_audience" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]">
                            <option v-for="[val, lbl] in audienceOptions" :key="val" :value="val">{{ lbl }}</option>
                        </select>
                    </label>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-700">Pendidikan Minimum
                        <input v-model="form.minimum_education" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" />
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Pengalaman
                        <input v-model="form.experience_requirement" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" />
                    </label>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-700">Tanggal Buka
                        <input v-model="form.open_at" type="datetime-local" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" />
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Tanggal Tutup
                        <input v-model="form.close_at" type="datetime-local" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" />
                    </label>
                </div>
                <p class="text-xs text-slate-400">Metode lamaran: In-Portal (wajib untuk lowongan kampus).</p>
            </fieldset>

            <div class="flex flex-wrap gap-3 pt-1">
                <button v-if="!editing && editable" type="button" class="rounded-lg bg-[#0061a5] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#004172]" @click="editing = true">Ubah</button>
                <button v-if="editing" type="submit" :disabled="saving" class="rounded-lg bg-[#0061a5] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#004172] disabled:opacity-60">
                    {{ saving ? 'Menyimpan…' : (mode === 'create' ? 'Simpan Draf' : 'Simpan Perubahan') }}
                </button>
                <p v-if="mode === 'edit' && !editable && editing === false" class="text-sm text-slate-500">Lowongan hanya dapat diubah saat berstatus Draf.</p>
            </div>
        </form>
    </AppShell>
</template>
