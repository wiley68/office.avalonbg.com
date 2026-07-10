<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useTranslations } from '@/composables/useTranslations';
import { dashboard } from '@/routes';
import { clearCache as dashboardClearCache } from '@/routes/dashboard';
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

const page = usePage();
const { t } = useTranslations();

const isAuthenticated = computed(() => page.props.auth.user !== null);

function refreshDashboardData(): void {
    router.post(
        dashboardClearCache().url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                router.visit(dashboard().url);
            },
        },
    );
}
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4 dark:border-sidebar-border"
    >
        <SidebarTrigger class="-ml-1 shrink-0" />
        <div class="flex min-w-0 flex-1 items-center overflow-hidden">
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <div
                v-if="pageTitle"
                class="hidden min-w-0 flex-col justify-center gap-0.5 overflow-hidden sm:flex"
            >
                <h1
                    class="m-0 max-w-48 truncate text-right text-sm leading-tight font-semibold tracking-tight text-foreground md:max-w-64"
                >
                    {{ pageTitle }}
                </h1>
                <p
                    v-if="pageDescription"
                    class="m-0 max-w-48 truncate text-right text-[11px] leading-tight whitespace-nowrap text-muted-foreground md:max-w-64 sm:text-xs"
                >
                    {{ pageDescription }}
                </p>
            </div>
            <div v-if="$slots.pageActions" class="flex shrink-0 items-center">
                <slot name="pageActions" />
            </div>
            <Button
                v-if="isAuthenticated"
                class="h-8 shrink-0 cursor-pointer px-3 text-sm"
                variant="ghost"
                @click="refreshDashboardData"
            >
                {{ t('dashboard.refresh_data') }}
            </Button>
        </div>
    </header>
</template>
