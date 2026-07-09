<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import dashboardRoutes from '@/routes/dashboard';
import type { BreadcrumbItem } from '@/types';

type StatsRow = {
    context: string;
    agent: string;
    up_count: number;
    down_count: number;
    total_feedback: number;
};

type SummaryRow = {
    context: string;
    up_count: number;
    down_count: number;
    total_feedback: number;
};

defineProps<{
    period: '7d' | '30d' | '90d' | 'all';
    summary_rows: SummaryRow[];
    rows: StatsRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Табло',
        href: dashboard(),
    },
    {
        title: 'Администрация',
        href: dashboardRoutes.admin.statistics.url(),
    },
    {
        title: 'Статистика',
        href: dashboardRoutes.admin.statistics.url(),
    },
];

const periodOptions: Array<{
    value: '7d' | '30d' | '90d' | 'all';
    label: string;
}> = [
    { value: '7d', label: 'Последни 7 дни' },
    { value: '30d', label: 'Последни 30 дни' },
    { value: '90d', label: 'Последни 90 дни' },
    { value: 'all', label: 'Всички' },
];

const contextLabel = (context: string): string =>
    context === 'notes' ? 'Бележки' : 'Офис координатор';
</script>

<template>
    <Head title="Статистика" />

    <AppLayout
        :breadcrumbs="breadcrumbs"
        page-title="Статистика"
        page-description="Обратна връзка за агент отговорите (👍/👎) по страница и агент."
    >
        <div class="space-y-4 p-4 md:p-6">
            <div class="flex flex-wrap items-center gap-2">
                <Link
                    v-for="option in periodOptions"
                    :key="option.value"
                    :href="
                        dashboardRoutes.admin.statistics.url({
                            query: { period: option.value },
                        })
                    "
                    class="rounded-md border px-3 py-1.5 text-sm transition"
                    :class="
                        period === option.value
                            ? 'border-sidebar-border bg-muted text-foreground'
                            : 'border-sidebar-border/60 text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                    "
                >
                    {{ option.label }}
                </Link>
            </div>

            <div
                class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background"
            >
                <div
                    class="border-b border-sidebar-border/60 bg-muted/20 px-4 py-2"
                >
                    <h2 class="text-sm font-semibold text-foreground">
                        Агрегирано по страница
                    </h2>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Страница</th>
                            <th class="px-4 py-3 font-medium">👍</th>
                            <th class="px-4 py-3 font-medium">👎</th>
                            <th class="px-4 py-3 font-medium">Общо</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in summary_rows"
                            :key="row.context"
                            class="border-t border-sidebar-border/60"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ contextLabel(row.context) }}
                            </td>
                            <td class="px-4 py-3">{{ row.up_count }}</td>
                            <td class="px-4 py-3">{{ row.down_count }}</td>
                            <td class="px-4 py-3 font-semibold">
                                {{ row.total_feedback }}
                            </td>
                        </tr>
                        <tr v-if="summary_rows.length === 0">
                            <td
                                colspan="4"
                                class="px-4 py-6 text-center text-muted-foreground"
                            >
                                Няма данни за избрания период.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background"
            >
                <div
                    class="border-b border-sidebar-border/60 bg-muted/20 px-4 py-2"
                >
                    <h2 class="text-sm font-semibold text-foreground">
                        Детайлно по агент
                    </h2>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Страница</th>
                            <th class="px-4 py-3 font-medium">Агент</th>
                            <th class="px-4 py-3 font-medium">👍</th>
                            <th class="px-4 py-3 font-medium">👎</th>
                            <th class="px-4 py-3 font-medium">Общо</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows"
                            :key="`${row.context}-${row.agent}`"
                            class="border-t border-sidebar-border/60"
                        >
                            <td class="px-4 py-3">
                                {{ contextLabel(row.context) }}
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">
                                {{ row.agent }}
                            </td>
                            <td class="px-4 py-3">{{ row.up_count }}</td>
                            <td class="px-4 py-3">{{ row.down_count }}</td>
                            <td class="px-4 py-3 font-semibold">
                                {{ row.total_feedback }}
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td
                                colspan="5"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                Няма събрана обратна връзка за избрания период.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
