<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import dashboardRoutes from '@/routes/dashboard';
import { notes as adminNotesExportRoute } from '@/routes/dashboard/admin/export';
import type { BreadcrumbItem } from '@/types';

type ExportTableRow = {
    key: string;
    label: string;
    row_count: number;
};

defineProps<{
    tables: ExportTableRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Композитор',
        href: dashboard(),
    },
    {
        title: 'Експорт',
        href: dashboardRoutes.admin.export.url(),
    },
];

const notesExportUrl = adminNotesExportRoute.url();
</script>

<template>
    <Head title="Експорт" />

    <AppLayout
        :breadcrumbs="breadcrumbs"
        page-title="Експорт на данни"
        page-description="Изтегляне на пълни експорти по таблици (само администратори)."
    >
        <div class="space-y-4 p-4 md:p-6">
            <div
                class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background"
            >
                <div
                    class="border-b border-sidebar-border/60 bg-muted/20 px-4 py-2"
                >
                    <h2 class="text-sm font-semibold text-foreground">
                        Таблици
                    </h2>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">#</th>
                            <th class="px-4 py-3 font-medium">Таблица</th>
                            <th class="px-4 py-3 font-medium">Редове</th>
                            <th class="px-4 py-3 font-medium">Управление</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, index) in tables"
                            :key="row.key"
                            class="border-t border-sidebar-border/60"
                        >
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ index + 1 }}
                            </td>
                            <td class="px-4 py-3 font-mono">{{ row.label }}</td>
                            <td class="px-4 py-3 tabular-nums">
                                {{ row.row_count }}
                            </td>
                            <td class="px-4 py-3">
                                <Button
                                    v-if="row.key === 'notes'"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                >
                                    <a :href="notesExportUrl">Експорт</a>
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="tables.length === 0">
                            <td
                                colspan="4"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                Няма конфигурирани таблици за експорт.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
