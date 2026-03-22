<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
    /** Заглавие на страницата в хедъра (до/под breadcrumbs), без отделен блок в съдържанието. */
    pageTitle?: string;
    pageDescription?: string;
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppLayout
        :breadcrumbs="breadcrumbs"
        :page-title="pageTitle"
        :page-description="pageDescription"
    >
        <template v-if="$slots.pageActions" #pageActions>
            <slot name="pageActions" />
        </template>
        <slot />
    </AppLayout>
</template>
