<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    ChartColumn,
    ChevronLeft,
    ChevronRight,
    FileSpreadsheet,
    FileText,
    List,
    Loader2,
    Plus,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import ProfitEntriesPanel from '@/components/profits/ProfitEntriesPanel.vue';
import ProfitEntryFormModal from '@/components/profits/ProfitEntryFormModal.vue';
import ProfitExportDialog from '@/components/profits/ProfitExportDialog.vue';
import ProfitStatsView from '@/components/profits/ProfitStatsView.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAppToast } from '@/composables/useAppToast';
import {
    profitsApiIndex,
    profitsApiStats,
} from '@/composables/useProfitsApiRoute';
import { useTranslations } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ProfitExportFormat } from '@/lib/profitExport';
import { dashboard } from '@/routes';
import { destroy, exportMethod, index } from '@/routes/profits';
import type { BreadcrumbItem } from '@/types';
import type {
    ProfitEntryItem,
    ProfitMonthResponse,
    ProfitMonthTotals,
    ProfitStatsMonth,
    ProfitStatsResponse,
    ProfitTypeKind,
} from '@/types/profits';

type ViewMode = 'list' | 'stats';
type StatsRangeMode = 'year' | 'custom';

const { t } = useTranslations();
const { showError, showMessage } = useAppToast();
const page = usePage();

const locale = computed(() =>
    typeof page.props.locale === 'string' ? page.props.locale : 'bg',
);

const currentMonth = (): string => {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
};

const parseMonthFromUrl = (): string => {
    const value = new URLSearchParams(window.location.search).get('month');

    if (value && /^\d{4}-\d{2}$/.test(value)) {
        return value;
    }

    return currentMonth();
};

const month = ref(parseMonthFromUrl());
const loading = ref(false);
const income = ref<ProfitEntryItem[]>([]);
const expense = ref<ProfitEntryItem[]>([]);
const totals = ref<ProfitMonthTotals>({
    income: '0.00',
    expense: '0.00',
    result: '0.00',
});
const period = ref({ from: '', to: '' });

const viewMode = ref<ViewMode>('list');
const statsRangeMode = ref<StatsRangeMode>('year');
const statsYear = ref(Number(month.value.slice(0, 4)));
const statsDateFrom = ref(`${statsYear.value}-01-01`);
const statsDateTo = ref(`${statsYear.value}-12-31`);
const statsMonths = ref<ProfitStatsMonth[]>([]);
const statsTotals = ref<ProfitMonthTotals>({
    income: '0.00',
    expense: '0.00',
    result: '0.00',
});
const statsPeriod = ref({ from: '', to: '' });

const showFormModal = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const editingEntry = ref<ProfitEntryItem | null>(null);
const defaultKind = ref<ProfitTypeKind>('income');

const showDeleteDialog = ref(false);
const entryToDelete = ref<ProfitEntryItem | null>(null);

const showExportDialog = ref(false);
const exportFormat = ref<ProfitExportFormat>('xlsx');

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('common.dashboard'),
        href: dashboard(),
    },
    {
        title: t('profits.title'),
        href: index(),
    },
]);

const monthLabel = computed(() => {
    const [year, monthPart] = month.value.split('-').map(Number);
    const date = new Date(year, monthPart - 1, 1);

    return new Intl.DateTimeFormat(locale.value, {
        month: 'long',
        year: 'numeric',
    }).format(date);
});

const formatAmount = (value: string): string => {
    const amount = Number(value);

    return new Intl.NumberFormat(locale.value, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number.isFinite(amount) ? amount : 0);
};

const activeTotals = computed(() =>
    viewMode.value === 'stats' ? statsTotals.value : totals.value,
);

const activePeriodLabel = computed(() => {
    if (viewMode.value === 'stats') {
        if (statsPeriod.value.from && statsPeriod.value.to) {
            return `${statsPeriod.value.from} – ${statsPeriod.value.to}`;
        }

        return String(statsYear.value);
    }

    return monthLabel.value;
});

const resultClass = computed(() => {
    const result = Number(activeTotals.value.result);

    if (result > 0) {
        return 'text-emerald-700 dark:text-emerald-400';
    }

    if (result < 0) {
        return 'text-destructive';
    }

    return 'text-foreground';
});

const exportDefaultDateFrom = computed(() => {
    if (viewMode.value === 'stats' && statsPeriod.value.from) {
        return statsPeriod.value.from;
    }

    return period.value.from || `${month.value}-01`;
});

const exportDefaultDateTo = computed(() => {
    if (viewMode.value === 'stats' && statsPeriod.value.to) {
        return statsPeriod.value.to;
    }

    if (period.value.to) {
        return period.value.to;
    }

    const [year, monthPart] = month.value.split('-').map(Number);
    const lastDay = new Date(year, monthPart, 0).getDate();

    return `${month.value}-${String(lastDay).padStart(2, '0')}`;
});

const openExport = (format: ProfitExportFormat): void => {
    exportFormat.value = format;
    showExportDialog.value = true;
};

const handleExportSuccess = (filename: string): void => {
    showMessage(
        t('common.success'),
        t('profits.export.success', { filename }),
    );
};

const handleExportError = (message: string): void => {
    showError(t('common.error'), message);
};

const syncMonthToUrl = (): void => {
    const url = new URL(window.location.href);
    url.searchParams.set('month', month.value);
    window.history.replaceState({}, '', url.toString());
};

const fetchMonth = async (): Promise<void> => {
    loading.value = true;

    try {
        const endpoint = profitsApiIndex({
            query: { month: month.value },
        }).url;

        const response = await fetch(endpoint, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(t('profits.errors.load'));
        }

        const payload = (await response.json()) as ProfitMonthResponse;

        income.value = payload.income;
        expense.value = payload.expense;
        totals.value = payload.totals;
        period.value = payload.period;
        syncMonthToUrl();
    } catch (error) {
        showError(
            t('common.error'),
            error instanceof Error ? error.message : t('profits.errors.load'),
        );
    } finally {
        loading.value = false;
    }
};

const fetchStats = async (): Promise<void> => {
    loading.value = true;

    try {
        const query =
            statsRangeMode.value === 'year'
                ? { year: String(statsYear.value) }
                : {
                      date_from: statsDateFrom.value,
                      date_to: statsDateTo.value,
                  };

        const endpoint = profitsApiStats({ query }).url;

        const response = await fetch(endpoint, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(t('profits.errors.load_stats'));
        }

        const payload = (await response.json()) as ProfitStatsResponse;

        statsMonths.value = payload.months;
        statsTotals.value = payload.totals;
        statsPeriod.value = payload.period;
    } catch (error) {
        showError(
            t('common.error'),
            error instanceof Error
                ? error.message
                : t('profits.errors.load_stats'),
        );
    } finally {
        loading.value = false;
    }
};

const refreshCurrentView = async (): Promise<void> => {
    if (viewMode.value === 'stats') {
        await fetchStats();
    } else {
        await fetchMonth();
    }
};

const shiftMonth = (delta: number): void => {
    const [year, monthPart] = month.value.split('-').map(Number);
    const date = new Date(year, monthPart - 1 + delta, 1);
    month.value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
};

const shiftYear = (delta: number): void => {
    statsYear.value += delta;
    statsDateFrom.value = `${statsYear.value}-01-01`;
    statsDateTo.value = `${statsYear.value}-12-31`;
};

const toggleViewMode = (): void => {
    if (viewMode.value === 'list') {
        viewMode.value = 'stats';
        statsYear.value = Number(month.value.slice(0, 4));
        statsRangeMode.value = 'year';
        statsDateFrom.value = `${statsYear.value}-01-01`;
        statsDateTo.value = `${statsYear.value}-12-31`;
    } else {
        viewMode.value = 'list';
    }
};

const openCreate = (kind: ProfitTypeKind = 'income'): void => {
    formMode.value = 'create';
    editingEntry.value = null;
    defaultKind.value = kind;
    showFormModal.value = true;
};

const openEdit = (entry: ProfitEntryItem): void => {
    formMode.value = 'edit';
    editingEntry.value = entry;
    defaultKind.value = entry.type.kind;
    showFormModal.value = true;
};

const requestDelete = (entry: ProfitEntryItem): void => {
    entryToDelete.value = entry;
    showDeleteDialog.value = true;
};

const cancelDelete = (): void => {
    entryToDelete.value = null;
    showDeleteDialog.value = false;
};

const confirmDelete = (): void => {
    if (entryToDelete.value === null) {
        return;
    }

    const entryId = entryToDelete.value.id;
    entryToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(destroy(entryId).url, {
        preserveScroll: true,
        onSuccess: async () => {
            await refreshCurrentView();
        },
    });
};

watch(month, async () => {
    if (viewMode.value === 'list') {
        await fetchMonth();
    }
});

watch(viewMode, async (mode, previousMode) => {
    if (mode === 'stats') {
        await fetchStats();
    } else if (previousMode === 'stats') {
        await fetchMonth();
    }
});

watch([statsRangeMode, statsYear, statsDateFrom, statsDateTo], async () => {
    if (viewMode.value === 'stats') {
        await fetchStats();
    }
});

onMounted(async () => {
    await fetchMonth();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('profits.title')" />

        <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
            <header
                class="shrink-0 border-b bg-background px-4 py-3 md:px-6"
            >
                <div
                    class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <template v-if="viewMode === 'list'">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                class="size-8"
                                :aria-label="t('profits.prev_month')"
                                @click="shiftMonth(-1)"
                            >
                                <ChevronLeft class="size-4" />
                            </Button>
                            <Input
                                v-model="month"
                                type="month"
                                class="w-42"
                                :aria-label="t('profits.month')"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                class="size-8"
                                :aria-label="t('profits.next_month')"
                                @click="shiftMonth(1)"
                            >
                                <ChevronRight class="size-4" />
                            </Button>
                            <span
                                class="hidden text-sm font-medium capitalize sm:inline"
                            >
                                {{ monthLabel }}
                            </span>
                        </template>

                        <template v-else>
                            <Select v-model="statsRangeMode">
                                <SelectTrigger class="w-40">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="year">
                                        {{ t('profits.stats.range_year') }}
                                    </SelectItem>
                                    <SelectItem value="custom">
                                        {{ t('profits.stats.range_custom') }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>

                            <template v-if="statsRangeMode === 'year'">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    class="size-8"
                                    :aria-label="t('profits.stats.prev_year')"
                                    @click="shiftYear(-1)"
                                >
                                    <ChevronLeft class="size-4" />
                                </Button>
                                <Input
                                    v-model.number="statsYear"
                                    type="number"
                                    min="2000"
                                    max="2100"
                                    class="w-24"
                                    :aria-label="t('profits.stats.year')"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    class="size-8"
                                    :aria-label="t('profits.stats.next_year')"
                                    @click="shiftYear(1)"
                                >
                                    <ChevronRight class="size-4" />
                                </Button>
                            </template>

                            <template v-else>
                                <div class="flex items-center gap-2">
                                    <Label
                                        for="stats-from"
                                        class="sr-only"
                                    >{{ t('profits.export.date_from') }}</Label>
                                    <Input
                                        id="stats-from"
                                        v-model="statsDateFrom"
                                        type="date"
                                        class="w-40"
                                    />
                                    <span class="text-muted-foreground">–</span>
                                    <Label
                                        for="stats-to"
                                        class="sr-only"
                                    >{{ t('profits.export.date_to') }}</Label>
                                    <Input
                                        id="stats-to"
                                        v-model="statsDateTo"
                                        type="date"
                                        class="w-40"
                                    />
                                </div>
                            </template>
                        </template>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="hidden sm:inline-flex"
                            @click="toggleViewMode"
                        >
                            <ChartColumn
                                v-if="viewMode === 'list'"
                                class="mr-2 size-4"
                            />
                            <List v-else class="mr-2 size-4" />
                            {{
                                viewMode === 'list'
                                    ? t('profits.stats.show')
                                    : t('profits.stats.hide')
                            }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="size-8 sm:hidden"
                            :aria-label="
                                viewMode === 'list'
                                    ? t('profits.stats.show')
                                    : t('profits.stats.hide')
                            "
                            @click="toggleViewMode"
                        >
                            <ChartColumn
                                v-if="viewMode === 'list'"
                                class="size-4"
                            />
                            <List v-else class="size-4" />
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            class="hidden sm:inline-flex"
                            @click="openExport('xlsx')"
                        >
                            <FileSpreadsheet class="mr-2 size-4" />
                            {{ t('profits.export.xlsx') }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="size-8 sm:hidden"
                            :aria-label="t('profits.export.xlsx')"
                            @click="openExport('xlsx')"
                        >
                            <FileSpreadsheet class="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="hidden sm:inline-flex"
                            @click="openExport('pdf')"
                        >
                            <FileText class="mr-2 size-4" />
                            {{ t('profits.export.pdf') }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="size-8 sm:hidden"
                            :aria-label="t('profits.export.pdf')"
                            @click="openExport('pdf')"
                        >
                            <FileText class="size-4" />
                        </Button>
                    </div>
                </div>
            </header>

            <div class="relative min-h-0 flex-1 overflow-hidden p-4 md:p-6">
                <div
                    v-if="loading"
                    class="absolute inset-0 z-10 flex items-center justify-center bg-background/60"
                >
                    <div
                        class="flex items-center gap-2 text-muted-foreground"
                    >
                        <Loader2 class="size-5 animate-spin" />
                        {{ t('common.table.loading') }}
                    </div>
                </div>

                <div
                    v-if="viewMode === 'list'"
                    class="grid h-full min-h-0 gap-4 lg:grid-cols-2"
                >
                    <ProfitEntriesPanel
                        :title="t('profits.income')"
                        :entries="income"
                        :empty-message="t('profits.empty_income')"
                        :format-amount="formatAmount"
                        @edit="openEdit"
                        @delete="requestDelete"
                    />
                    <ProfitEntriesPanel
                        :title="t('profits.expense')"
                        :entries="expense"
                        :empty-message="t('profits.empty_expense')"
                        :format-amount="formatAmount"
                        @edit="openEdit"
                        @delete="requestDelete"
                    />
                </div>

                <div v-else class="h-full min-h-0">
                    <ProfitStatsView
                        :months="statsMonths"
                        :totals="statsTotals"
                        :locale="locale"
                        :format-amount="formatAmount"
                    />
                </div>
            </div>

            <footer
                class="shrink-0 border-t bg-background px-4 py-3 md:px-6"
            >
                <div
                    class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div
                        class="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div>
                            <p class="text-xs text-muted-foreground">
                                {{ t('profits.summary.period') }}
                            </p>
                            <p class="text-sm font-medium capitalize">
                                {{ activePeriodLabel }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                {{ t('profits.summary.income') }}
                            </p>
                            <p class="text-sm font-semibold tabular-nums">
                                {{ formatAmount(activeTotals.income) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                {{ t('profits.summary.expense') }}
                            </p>
                            <p class="text-sm font-semibold tabular-nums">
                                {{ formatAmount(activeTotals.expense) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                {{ t('profits.summary.result') }}
                            </p>
                            <p
                                class="text-sm font-semibold tabular-nums"
                                :class="resultClass"
                            >
                                {{ formatAmount(activeTotals.result) }}
                            </p>
                        </div>
                    </div>

                    <Button
                        type="button"
                        class="shrink-0 self-start lg:self-auto"
                        @click="openCreate('income')"
                    >
                        <Plus class="mr-2 size-4" />
                        {{ t('profits.add') }}
                    </Button>
                </div>
            </footer>
        </div>

        <ProfitEntryFormModal
            v-model:open="showFormModal"
            :mode="formMode"
            :entry="editingEntry"
            :default-kind="defaultKind"
            @saved="refreshCurrentView"
        />

        <ProfitExportDialog
            v-model:open="showExportDialog"
            :export-url="exportMethod().url"
            :format="exportFormat"
            :default-date-from="exportDefaultDateFrom"
            :default-date-to="exportDefaultDateTo"
            @success="handleExportSuccess"
            @error="handleExportError"
        />

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            :title="t('users.delete_confirm_title')"
            :description="t('profits.delete_confirm')"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </AppLayout>
</template>
