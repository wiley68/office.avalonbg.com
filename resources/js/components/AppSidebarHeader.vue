<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
        pageTitle?: string;
        pageDescription?: string;
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4 dark:border-sidebar-border"
    >
        <SidebarTrigger class="-ml-1 shrink-0" />
        <div
            class="flex min-w-0 items-center overflow-hidden"
            :class="
                pageTitle
                    ? 'max-w-[min(100%,50%)] shrink basis-auto lg:max-w-[min(100%,45%)]'
                    : 'flex-1'
            "
        >
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <div
            v-if="pageTitle"
            class="flex min-h-0 min-w-0 flex-1 items-center justify-end gap-2 self-stretch sm:pl-4"
        >
            <div
                class="flex h-full min-h-0 min-w-0 flex-1 flex-col justify-center gap-0.5 overflow-hidden py-0.5"
            >
                <h1
                    class="m-0 w-full min-w-0 truncate text-right text-sm leading-tight font-semibold tracking-tight text-foreground"
                >
                    {{ pageTitle }}
                </h1>
                <p
                    v-if="pageDescription"
                    class="m-0 w-full min-w-0 truncate text-right text-[11px] leading-tight whitespace-nowrap text-muted-foreground sm:text-xs"
                >
                    {{ pageDescription }}
                </p>
            </div>
            <div v-if="$slots.pageActions" class="flex shrink-0 items-center">
                <slot name="pageActions" />
            </div>
        </div>
    </header>
</template>
