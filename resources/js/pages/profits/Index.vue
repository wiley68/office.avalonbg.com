<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Loader2, Plus } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import ProfitEntriesPanel from '@/components/profits/ProfitEntriesPanel.vue';
import ProfitEntryFormModal from '@/components/profits/ProfitEntryFormModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAppToast } from '@/composables/useAppToast';
import { profitsApiIndex } from '@/composables/useProfitsApiRoute';
import { useTranslations } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { destroy, index } from '@/routes/profits';
import type { BreadcrumbItem } from '@/types';
import type {
    ProfitEntryItem,
    ProfitMonthResponse,
    ProfitMonthTotals,
    ProfitTypeKind,
} from '@/types/profits';

const { t } = useTranslations();
const { showError } = useAppToast();
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

const showFormModal = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const editingEntry = ref<ProfitEntryItem | null>(null);
const defaultKind = ref<ProfitTypeKind>('income');

const showDeleteDialog = ref(false);
const entryToDelete = ref<ProfitEntryItem | null>(null);

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

const resultClass = computed(() => {
    const result = Number(totals.value.result);

    if (result > 0) {
        return 'text-emerald-700 dark:text-emerald-400';
    }

    if (result < 0) {
        return 'text-destructive';
    }

    return 'text-foreground';
});

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

const shiftMonth = (delta: number): void => {
    const [year, monthPart] = month.value.split('-').map(Number);
    const date = new Date(year, monthPart - 1 + delta, 1);
    month.value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
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
            await fetchMonth();
        },
    });
};

watch(month, async () => {
    await fetchMonth();
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
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-2">
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
                                {{ monthLabel }}
                                <span
                                    v-if="period.from"
                                    class="font-normal text-muted-foreground"
                                >
                                    ({{ period.from }} – {{ period.to }})
                                </span>
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                {{ t('profits.summary.income') }}
                            </p>
                            <p class="text-sm font-semibold tabular-nums">
                                {{ formatAmount(totals.income) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                {{ t('profits.summary.expense') }}
                            </p>
                            <p class="text-sm font-semibold tabular-nums">
                                {{ formatAmount(totals.expense) }}
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
                                {{ formatAmount(totals.result) }}
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
            @saved="fetchMonth"
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
