<script setup lang="ts">
import { computed } from 'vue';
import { SidebarInset } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    variant?: AppVariant;
    class?: string;
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'sidebar',
});
const className = computed(() => props.class);
</script>

<template>
    <SidebarInset v-if="props.variant === 'sidebar'" :class="className">
        <slot />
    </SidebarInset>
    <main
        v-else
        class="mx-auto flex h-[calc(100svh-10px)] max-h-[calc(100svh-10px)] min-h-0 w-full max-w-7xl flex-1 flex-col gap-4 overflow-hidden rounded-xl"
        :class="className"
    >
        <slot />
    </main>
</template>
