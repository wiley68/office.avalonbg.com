<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LayoutGrid } from 'lucide-vue-next';
import {
    NavigationMenuItem,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';

type Props = {
    variant: 'sheet' | 'bar';
};

defineProps<Props>();

const { isCurrentUrl, whenCurrentUrl } = useCurrentUrl();

const activeItemStyles =
    'text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100';
</script>

<template>
    <template v-if="variant === 'sheet'">
        <nav class="-mx-3 space-y-1">
            <Link
                :href="dashboard()"
                class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-accent"
                :class="whenCurrentUrl(dashboard(), activeItemStyles)"
            >
                <LayoutGrid class="h-5 w-5" />
                Табло
            </Link>
        </nav>
    </template>

    <template v-else>
        <NavigationMenuItem class="relative flex h-full items-center">
            <Link
                :class="[
                    navigationMenuTriggerStyle(),
                    whenCurrentUrl(dashboard(), activeItemStyles),
                    'h-9 cursor-pointer px-3',
                ]"
                :href="dashboard()"
            >
                <LayoutGrid class="mr-2 h-4 w-4" />
                Табло
            </Link>
            <div
                v-if="isCurrentUrl(dashboard())"
                class="absolute bottom-0 left-0 h-0.5 w-full translate-y-px bg-black dark:bg-white"
            />
        </NavigationMenuItem>
    </template>
</template>
