<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
    pageTitle?: string;
    pageDescription?: string;
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent
            variant="sidebar"
            class="flex min-h-0 flex-1 flex-col overflow-hidden"
        >
            <AppSidebarHeader
                :breadcrumbs="breadcrumbs"
                :page-title="pageTitle"
                :page-description="pageDescription"
            >
                <template v-if="$slots.pageActions" #pageActions>
                    <slot name="pageActions" />
                </template>
            </AppSidebarHeader>
            <slot />
        </AppContent>
    </AppShell>
</template>
