<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { authRequest, errorText } from '@/lib/auth'

type Field = { key: string; label: string; type?: 'text' | 'date' | 'number' | 'boolean' | 'linkType'; required?: boolean }
type Item = Record<string, unknown>

const props = defineProps<{ title: string; endpoint: string; fields: Field[]; items: Item[]; help?: string }>()
const emit = defineEmits<{ saved: [items: Item[]] }>()
const rows = ref<Item[]>([])
const busy = ref(false)
const message = ref('')

function clone(items: Item[]) { return items.map((item) => ({ ...item })) }
watch(() => props.items, (items) => { rows.value = clone(items) }, { immediate: true, deep: true })

const hasItems = computed(() => rows.value.length > 0)

function add() {
    const row: Item = {}
    for (const field of props.fields) {
        if (field.type === 'boolean') row[field.key] = false
        else if (field.type === 'number') row[field.key] = field.key === 'sort_order' ? rows.value.length + 1 : null
        else row[field.key] = ''
    }
    rows.value.push(row)
}

function remove(index: number) { rows.value.splice(index, 1) }

async function save() {
    busy.value = true; message.value = ''
    const { response, payload } = await authRequest(props.endpoint, { items: rows.value }, 'PUT')
    busy.value = false
    if (!response.ok) { message.value = errorText(payload); return }
    const data = payload.data as { items?: Item[] } | undefined
    const saved = data?.items ?? []
    rows.value = clone(saved); emit('saved', saved); message.value = 'Bagian profil disimpan.'
}
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" :aria-labelledby="`section-${endpoint}`">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h2 :id="`section-${endpoint}`" class="text-lg font-semibold text-[#002045]">{{ title }}</h2><p v-if="help" class="mt-1 text-sm text-slate-600">{{ help }}</p></div>
            <button type="button" class="rounded-lg border border-[#0061a5] px-3 py-2 text-sm font-semibold text-[#004172] hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0061a5]" @click="add">Tambah</button>
        </div>
        <p v-if="!hasItems" class="mt-4 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">Belum ada data pada bagian ini.</p>
        <div v-for="(row, index) in rows" :key="String(row.id ?? `new-${index}`)" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div class="grid gap-3 md:grid-cols-2">
                <template v-for="field in fields" :key="field.key">
                    <label class="block text-sm font-medium text-slate-700">
                        {{ field.label }}<span v-if="field.required" aria-hidden="true"> *</span>
                        <select v-if="field.type === 'boolean'" v-model="row[field.key]" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]">
                            <option :value="true">Ya</option><option :value="false">Tidak</option>
                        </select>
                        <select v-else-if="field.type === 'linkType'" v-model="row[field.key]" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]">
                            <option value="LINKEDIN">LinkedIn</option><option value="PORTFOLIO">Portfolio</option><option value="PERSONAL_WEBSITE">Website pribadi</option><option value="PUBLICATION">Publikasi</option><option value="OTHER">Lainnya</option>
                        </select>
                        <input v-else v-model="row[field.key]" :type="field.type === 'date' ? 'date' : field.type === 'number' ? 'number' : 'text'" :required="field.required" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-[#0061a5] focus:ring-[#0061a5]" />
                    </label>
                </template>
            </div>
            <button type="button" class="mt-3 text-sm font-semibold text-[#93000a] hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#93000a]" @click="remove(index)">Hapus baris</button>
        </div>
        <p v-if="message" class="mt-4 text-sm" :class="message === 'Bagian profil disimpan.' ? 'text-green-700' : 'text-[#93000a]'" role="status">{{ message }}</p>
        <button type="button" class="mt-5 rounded-lg bg-[#0061a5] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#004172] disabled:cursor-wait disabled:opacity-60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0061a5]" :disabled="busy" @click="save">{{ busy ? 'Menyimpan…' : `Simpan ${title}` }}</button>
    </section>
</template>
