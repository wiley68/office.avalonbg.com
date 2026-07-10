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

const props = withDefaults(
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

const showRefreshDataLink = computed(
    () =>
        isAuthenticated.value &&
        !(props.pageTitle && props.pageDescription),
);

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
        class="flex min-h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 py-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:min-h-12 md:px-4 dark:border-sidebar-border"
    >
        <SidebarTrigger class="-ml-1 shrink-0" />
        <div
            class="flex min-w-0 flex-1 items-center gap-4 md:gap-6"
        >
            <div
                v-if="breadcrumbs && breadcrumbs.length > 0"
                class="flex min-w-0 max-w-[min(100%,40%)] shrink-0 items-center overflow-hidden"
            >
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
            <div
                class="flex min-w-0 flex-1 items-center justify-end gap-2"
            >
                <div
                    v-if="pageTitle"
                    class="hidden min-w-0 w-full flex-col items-end justify-center gap-0.5 sm:flex"
                >
                    <h1
                        class="m-0 w-full text-right text-sm leading-tight font-semibold tracking-tight text-foreground"
                    >
                        {{ pageTitle }}
                    </h1>
                    <p
                        v-if="pageDescription"
                        class="m-0 w-full text-right text-[11px] leading-snug text-pretty text-muted-foreground sm:text-xs"
                    >
                        {{ pageDescription }}
                    </p>
                </div>
                <div
                    v-if="$slots.pageActions"
                    class="flex shrink-0 items-center"
                >
                    <slot name="pageActions" />
                </div>
                <Button
                    v-if="showRefreshDataLink"
                    class="h-8 shrink-0 cursor-pointer px-3 text-sm"
                    variant="ghost"
                    @click="refreshDashboardData"
                >
                    {{ t('dashboard.refresh_data') }}
                </Button>
            </div>
        </div>
    </header>
</template>
