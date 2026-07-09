<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Табло',
        href: dashboard(),
    },
];
</script>

<template>
    <Head title="Табло" />

    <AppLayout
        :breadcrumbs="breadcrumbs"
        page-title="Табло"
    >
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        />
    </AppLayout>
</template>
