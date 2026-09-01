<script setup lang="ts">
/**
 * Recruiter "Profil Perusahaan" (Recruiter Company & Vacancy Frontend Slice
 * v3). Follows Stitch `recruiter/lengkapi-profil-perusahaan` for the field set
 * and the shared AppShell/card language.
 *
 * Read + create + update only. The profile is editable exactly while the
 * company is DRAFT or REVISION_REQUIRED — the same window the frozen
 * `UpdateCompanyProfile` action accepts; any other status renders read-only
 * (backend answers `COMPANY_INVALID_TRANSITION` regardless). `internal_note`
 * and Career-Center-internal fields are never delivered to this page.
 * "Terverifikasi" is a verification state and is shown as distinct from
 * Mitra Kampus — this slice implements no Kemitraan surface.
 */
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'
import AppShell from '@/layouts/AppShell.vue'
import { authRequest, errorText } from '@/lib/auth'
import { companyStatusBadgeClass, companyStatusLabel, statusLabel } from '@/lib/labels'

interface RefRow { id: number; code?: string | null; name: string; area_type?: string | null; parent_geographic_area_id?: number | null }
interface Company {
    id: number; name: string; slug: string | null
    organization_type_id: number | null; industry_id: number | null
    website: string | null; official_email: string | null; official_phone: string | null
    address: string | null; province_geographic_area_id: number | null; city_geographic_area_id: number | null
    legal_identifier: string | null; verification_status: string
    verified_at: string | null; suspended_at: string | null; mitra_kampus_active: boolean
}

const props = defineProps<{
    company: Company | null
    can_create: boolean
    can_edit: boolean
    reference_data: { organization_types: RefRow[]; industries: RefRow[]; geographic_areas: RefRow[] }
}>()

const provinces = computed(() => props.reference_data.geographic_areas.filter((a) => a.area_type === 'PROVINCE'))
const cities = computed(() => props.reference_data.geographic_areas.filter(
    (a) => a.area_type === 'CITY' && (!form.province_geographic_area_id || a.parent_geographic_area_id === Number(form.province_geographic_area_id)),
))

const editing = ref(props.company === null)
const saving = ref(false)
const message = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const form = reactive({
    name: props.company?.name ?? '',
    organization_type_id: props.company?.organization_type_id ?? null as number | null,
    industry_id: props.company?.industry_id ?? null as number | null,
    website: props.company?.website ?? '',
    official_email: props.company?.official_email ?? '',
    official_phone: props.company?.official_phone ?? '',
    address: props.company?.address ?? '',
    province_geographic_area_id: props.company?.province_geographic_area_id ?? null as number | null,
    city_geographic_area_id: props.company?.city_geographic_area_id ?? null as number | null,
    legal_identifier: props.company?.legal_identifier ?? '',
})

function payload() {
    const nullable = (v: unknown) => (v === '' || v === null ? null : v)
    return {
        name: form.name,
        organization_type_id: nullable(form.organization_type_id),
        industry_id: nullable(form.industry_id),
        website: nullable(form.website),
        official_email: nullable(form.official_email),
        official_phone: nullable(form.official_phone),
        address: nullable(form.address),
        province_geographic_area_id: nullable(form.province_geographic_area_id),
        city_geographic_area_id: nullable(form.city_geographic_area_id),
        legal_identifier: nullable(form.legal_identifier),
    }
}

async function save() {
    saving.value = true
    message.value = ''
    fieldErrors.value = {}
    const isCreate = props.company === null
    const { response, payload: body } = await authRequest(
        isCreate ? '/companies' : `/companies/${props.company!.id}`,
        payload(),
        isCreate ? 'POST' : 'PATCH',
    )
    saving.value = false
    if (!response.ok) {
        message.value = errorText(body)
        fieldErrors.value = body.error?.details?.fields ?? {}
        return
    }
    router.reload()
}

function cancelEdit() {
    editing.value = false
    message.value = ''
    fieldErrors.value = {}
}
</script>

<template>
    <Head title="Profil Perusahaan" />
    <AppShell persona="recruiter" active="profil-perusahaan" title="Profil Perusahaan">
        <h1 class="text-3xl font-bold tracking-tight text-[#002045]">Profil Perusahaan</h1>
        <p class="mt-2 max-w-2xl text-slate-600">Data perusahaan yang digunakan Career Center untuk verifikasi. Pastikan sesuai dengan dokumen resmi perusahaan.</p>

        <p v-if="message" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-[#93000a]" role="alert">{{ message }}</p>

        <!-- No company yet -->
        <div v-if="!company && !can_create" class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
            Akun Anda belum memiliki akses profil perusahaan.
        </div>

        <template v-else>
            <div v-if="company" class="mt-6 flex flex-wrap items-center gap-3">
                <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="companyStatusBadgeClass[company.verification_status] ?? 'bg-slate-100 text-slate-700'">
                    {{ statusLabel(companyStatusLabel, company.verification_status) }}
                </span>
                <span class="text-xs text-slate-500">
                    Kemitraan Kampus: {{ company.mitra_kampus_active ? 'Aktif' : 'Tidak aktif' }} — status kemitraan terpisah dari verifikasi.
                </span>
                <Link href="/status-verifikasi" class="text-xs font-semibold text-[#0061a5] hover:underline">Lihat Status Verifikasi</Link>
            </div>

            <p v-if="company && !can_edit" class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                Profil hanya dapat diubah saat berstatus "Draf" atau "Perlu Perbaikan". Untuk perubahan pada status saat ini, hubungi Career Center.
            </p>

            <form class="mt-6 space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="save">
                <fieldset :disabled="!editing || saving" class="space-y-5">
                    <label class="block text-sm font-medium text-slate-700">Nama Perusahaan <span class="text-red-500">*</span>
                        <input v-model="form.name" required class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" placeholder="Contoh: PT Teknologi Nusantara Maju" />
                        <span v-if="fieldErrors.name" class="mt-1 block text-xs text-red-600">{{ fieldErrors.name[0] }}</span>
                    </label>

                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="block text-sm font-medium text-slate-700">Jenis Organisasi
                            <select v-model="form.organization_type_id" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]">
                                <option :value="null">Pilih jenis organisasi</option>
                                <option v-for="o in reference_data.organization_types" :key="o.id" :value="o.id">{{ o.name }}</option>
                            </select>
                        </label>
                        <label class="block text-sm font-medium text-slate-700">Industri
                            <select v-model="form.industry_id" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]">
                                <option :value="null">Pilih sektor industri</option>
                                <option v-for="i in reference_data.industries" :key="i.id" :value="i.id">{{ i.name }}</option>
                            </select>
                        </label>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="block text-sm font-medium text-slate-700">Website
                            <input v-model="form.website" type="url" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" placeholder="https://www.perusahaananda.com" />
                            <span v-if="fieldErrors.website" class="mt-1 block text-xs text-red-600">{{ fieldErrors.website[0] }}</span>
                        </label>
                        <label class="block text-sm font-medium text-slate-700">Email Resmi Perusahaan
                            <input v-model="form.official_email" type="email" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" placeholder="hrd@perusahaananda.com" />
                            <span v-if="fieldErrors.official_email" class="mt-1 block text-xs text-red-600">{{ fieldErrors.official_email[0] }}</span>
                        </label>
                    </div>

                    <label class="block text-sm font-medium text-slate-700">Nomor Telepon
                        <input v-model="form.official_phone" inputmode="tel" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" />
                    </label>

                    <label class="block text-sm font-medium text-slate-700">Alamat Lengkap
                        <textarea v-model="form.address" rows="3" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" placeholder="Masukkan alamat lengkap kantor pusat" />
                    </label>

                    <div class="grid gap-5 md:grid-cols-2">
                        <label class="block text-sm font-medium text-slate-700">Provinsi
                            <select v-model="form.province_geographic_area_id" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]">
                                <option :value="null">Pilih provinsi</option>
                                <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </label>
                        <label class="block text-sm font-medium text-slate-700">Kota / Kabupaten
                            <select v-model="form.city_geographic_area_id" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]">
                                <option :value="null">Pilih kota / kabupaten</option>
                                <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </label>
                    </div>

                    <label class="block text-sm font-medium text-slate-700">Identifikasi Legal (opsional)
                        <input v-model="form.legal_identifier" class="mt-1 block w-full rounded-lg border-slate-300 focus:border-[#0061a5] focus:ring-[#0061a5]" />
                    </label>
                </fieldset>

                <div class="flex flex-wrap gap-3 pt-1">
                    <button v-if="!editing && can_edit" type="button" class="rounded-lg bg-[#0061a5] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#004172]" @click="editing = true">Ubah Profil</button>
                    <button v-if="editing" type="submit" :disabled="saving" class="rounded-lg bg-[#0061a5] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#004172] disabled:opacity-60">
                        {{ saving ? 'Menyimpan…' : (company ? 'Simpan Perubahan' : 'Simpan & Lanjutkan') }}
                    </button>
                    <button v-if="editing && company" type="button" class="text-sm font-medium text-slate-600 hover:underline" @click="cancelEdit">Batal</button>
                </div>
            </form>
        </template>
    </AppShell>
</template>
