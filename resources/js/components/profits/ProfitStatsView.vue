<script setup lang="ts">
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    Title,
    Tooltip,
} from 'chart.js';
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import { useTranslations } from '@/composables/useTranslations';
import type { ProfitMonthTotals, ProfitStatsMonth } from '@/types/profits';

ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
    Tooltip,
    Legend,
);

type Props = {
    months: ProfitStatsMonth[];
    totals: ProfitMonthTotals;
    locale: string;
    formatAmount: (value: string) => string;
};

const props = defineProps<Props>();
const { t } = useTranslations();

const monthLabels = computed(() =>
    props.months.map((row) => {
        const [year, monthPart] = row.month.split('-').map(Number);
        const date = new Date(year, monthPart - 1, 1);

        return new Intl.DateTimeFormat(props.locale, {
            month: 'short',
            year: '2-digit',
        }).format(date);
    }),
);

const chartData = computed(() => ({
    labels: monthLabels.value,
    datasets: [
        {
            label: t('profits.income'),
            backgroundColor: 'rgba(16, 185, 129, 0.75)',
            borderColor: 'rgb(16, 185, 129)',
            borderWidth: 1,
            data: props.months.map((row) => Number(row.income)),
        },
        {
            label: t('profits.expense'),
            backgroundColor: 'rgba(239, 68, 68, 0.7)',
            borderColor: 'rgb(239, 68, 68)',
            borderWidth: 1,
            data: props.months.map((row) => Number(row.expense)),
        },
        {
            label: t('profits.summary.result'),
            backgroundColor: 'rgba(59, 130, 246, 0.65)',
            borderColor: 'rgb(59, 130, 246)',
            borderWidth: 1,
            data: props.months.map((row) => Number(row.result)),
        },
    ],
}));

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top' as const,
        },
        tooltip: {
            callbacks: {
                label: (context: {
                    dataset: { label?: string };
                    parsed: { y: number | null };
                }) => {
                    const label = context.dataset.label ?? '';
                    const value = context.parsed.y ?? 0;

                    return `${label}: ${props.formatAmount(String(value))}`;
                },
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: (value: string | number) =>
                    props.formatAmount(String(value)),
            },
        },
    },
}));
</script>

<template>
    <section
        class="flex h-full min-h-0 flex-col overflow-hidden rounded-xl border"
    >
        <div
            class="shrink-0 border-b bg-muted/30 px-4 py-3 text-sm font-semibold tracking-tight"
        >
            {{ t('profits.stats.title') }}
        </div>

        <div class="min-h-0 flex-1 overflow-auto p-4">
            <div class="relative h-[min(28rem,70vh)] w-full">
                <Bar :data="chartData" :options="chartOptions" />
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="w-full min-w-md text-sm">
                    <thead class="border-b text-left text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">
                                {{ t('profits.month') }}
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                {{ t('profits.income') }}
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                {{ t('profits.expense') }}
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                {{ t('profits.summary.result') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in months"
                            :key="row.month"
                            class="border-b last:border-b-0"
                        >
                            <td class="px-3 py-2 whitespace-nowrap">
                                {{ row.month }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ formatAmount(row.income) }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ formatAmount(row.expense) }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ formatAmount(row.result) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t font-semibold">
                        <tr>
                            <td class="px-3 py-2">
                                {{ t('profits.stats.period_total') }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ formatAmount(totals.income) }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ formatAmount(totals.expense) }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ formatAmount(totals.result) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </section>
</template>
