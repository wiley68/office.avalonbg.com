<script setup lang="ts">
defineProps<{
    open: boolean;
}>();
</script>

<template>
    <svg
        v-if="open"
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        class="ml-auto size-4 shrink-0"
        aria-hidden="true"
    >
        <title>chevron-down</title>
        <path
            d="M7.41,8.58L12,13.17L16.59,8.58L18,10L12,16L6,10L7.41,8.58Z"
        />
    </svg>
    <svg
        v-else
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        class="ml-auto size-4 shrink-0"
        aria-hidden="true"
    >
        <title>chevron-up</title>
        <path
            d="M7.41,15.41L12,10.83L16.59,15.41L18,14L12,8L6,14L7.41,15.41Z"
        />
    </svg>
</template>
