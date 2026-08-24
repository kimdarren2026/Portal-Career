<script setup lang="ts">
defineProps<{ message?: string; tone?: 'error' | 'success' | 'info' }>()
</script>

<template>
    <p v-if="message" class="mt-5 rounded-lg border px-3 py-2 text-sm" :class="{
        'border-red-200 bg-red-50 text-red-800': tone === 'error',
        'border-emerald-200 bg-emerald-50 text-emerald-800': tone === 'success',
        'border-blue-200 bg-blue-50 text-blue-800': tone === 'info',
    }" role="status" aria-live="polite">{{ message }}</p>
</template>
