<script setup lang="ts">
withDefaults(defineProps<{ loading?: boolean; type?: 'button' | 'submit' }>(), { loading: false, type: 'submit' })
</script>

<template>
    <button :type="type" :disabled="loading" class="mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-[#0b355d] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#082846] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0b355d] disabled:cursor-not-allowed disabled:opacity-60">
        <span v-if="loading" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" aria-hidden="true" />
        <slot />
    </button>
</template>
