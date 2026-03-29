<script setup lang="ts">
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    active: boolean;
}>();

type WarrantyRow = {
    id: number;
    product: string | null;
    sernum: string | null;
    date_sell: string | null;
    client_label: string | null;
};

type WarrantiesApiResponse = {
    data?: WarrantyRow[];
    meta?: {
        current_page?: number;
        per_page?: number;
        total?: number;
        last_page?: number;
        from?: number | null;
        to?: number | null;
    };
};

const API_BASE = '/api/warranty-cards';

const warranties = ref<WarrantyRow[]>([]);
const loading = ref(false);
const listError = ref<string | null>(null);
const searchQuery = ref('');
type SortColumn = 'id' | 'product' | 'date_sell';
const sortColumn = ref<SortColumn>('id');
const sortDirection = ref<'asc' | 'desc'>('desc');
const currentPage = ref(1);
const perPage = ref(20);
const total = ref(0);
const lastPage = ref(1);
const from = ref<number | null>(null);
const to = ref<number | null>(null);

const parseJsonErrors = async (response: Response): Promise<string> => {
    try {
        const data = (await response.json()) as {
            message?: string;
            errors?: Record<string, string[]>;
        };

        if (data.errors) {
            return Object.values(data.errors).flat().join(' ');
        }

        if (data.message) {
            return data.message;
        }
    } catch {
        //
    }

    return `Грешка ${response.status}. Опитайте отново.`;
};

const formatDateSell = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    const d = value.slice(0, 10);

    return d.length === 10 ? d : value;
};

const loadWarranties = async (page = currentPage.value): Promise<void> => {
    loading.value = true;
    listError.value = null;

    try {
        const params = new URLSearchParams({
            page: String(page),
            per_page: String(perPage.value),
            sort: sortColumn.value,
            direction: sortDirection.value,
        });

        const q = searchQuery.value.trim();

        if (q.length > 0) {
            params.set('q', q);
        }

        const response = await fetch(`${API_BASE}?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            listError.value = await parseJsonErrors(response);

            return;
        }

        const data = (await response.json()) as WarrantiesApiResponse;
        warranties.value = data.data ?? [];
        currentPage.value = data.meta?.current_page ?? page;
        perPage.value = data.meta?.per_page ?? perPage.value;
        total.value = data.meta?.total ?? warranties.value.length;
        lastPage.value = data.meta?.last_page ?? 1;
        from.value = data.meta?.from ?? null;
        to.value = data.meta?.to ?? null;
    } catch {
        listError.value = 'Неуспешно зареждане на гаранционните карти.';
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.active,
    (active) => {
        if (active) {
            void loadWarranties(1);
        }
    },
    { immediate: true },
);

const toggleSort = (column: SortColumn): void => {
    if (sortColumn.value !== column) {
        sortColumn.value = column;
        sortDirection.value = 'desc';
    } else {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    }

    void loadWarranties(1);
};

const sortIcon = (column: SortColumn) => {
    if (sortColumn.value !== column) {
        return ArrowUpDown;
    }

    return sortDirection.value === 'asc' ? ArrowUp : ArrowDown;
};

watch(searchQuery, () => {
    void loadWarranties(1);
});

const canGoPrev = () => currentPage.value > 1 && !loading.value;
const canGoNext = () => currentPage.value < lastPage.value && !loading.value;

const goToPreviousPage = (): void => {
    if (!canGoPrev()) {
        return;
    }

    void loadWarranties(currentPage.value - 1);
};

const goToNextPage = (): void => {
    if (!canGoNext()) {
        return;
    }

    void loadWarranties(currentPage.value + 1);
};

const searchFieldRoot = ref<HTMLElement | null>(null);

const focusSearchQuery = (): void => {
    const input = searchFieldRoot.value?.querySelector<HTMLInputElement>(
        'input[data-slot="input"]',
    );
    input?.focus({ preventScroll: true });
};

defineExpose({
    focusSearchQuery,
});
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-auto p-4 md:p-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div ref="searchFieldRoot" class="w-full min-w-0 sm:max-w-sm">
                <Input
                    v-model="searchQuery"
                    type="search"
                    placeholder="Търсене по продукт, сериен номер или клиент…"
                    autocomplete="off"
                    class="w-full"
                    aria-label="Търсене в гаранционните карти"
                />
            </div>
        </div>

        <p v-if="listError" class="text-sm text-destructive">
            {{ listError }}
        </p>

        <div
            class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background"
        >
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="-ml-2 h-8 gap-1 px-2 font-medium"
                                    type="button"
                                    @click="toggleSort('id')"
                                >
                                    ID
                                    <component
                                        :is="sortIcon('id')"
                                        class="h-3.5 w-3.5 opacity-70"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </th>
                            <th class="px-4 py-3 font-medium">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="-ml-2 h-8 gap-1 px-2 font-medium"
                                    type="button"
                                    @click="toggleSort('product')"
                                >
                                    Продукт
                                    <component
                                        :is="sortIcon('product')"
                                        class="h-3.5 w-3.5 opacity-70"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </th>
                            <th class="px-4 py-3 font-medium">Сер. номер</th>
                            <th class="px-4 py-3 font-medium">Клиент</th>
                            <th class="px-4 py-3 font-medium">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="-ml-2 h-8 gap-1 px-2 font-medium"
                                    type="button"
                                    @click="toggleSort('date_sell')"
                                >
                                    Дата на издаване
                                    <component
                                        :is="sortIcon('date_sell')"
                                        class="h-3.5 w-3.5 opacity-70"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-if="loading">
                            <tr>
                                <td
                                    colspan="5"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    Зареждане…
                                </td>
                            </tr>
                        </template>
                        <template v-else-if="warranties.length === 0">
                            <tr>
                                <td
                                    colspan="5"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    Няма записи.
                                </td>
                            </tr>
                        </template>
                        <template v-else>
                            <tr
                                v-for="row in warranties"
                                :key="row.id"
                                class="border-t border-sidebar-border/60"
                            >
                                <td class="px-4 py-3 font-mono text-xs">
                                    {{ row.id }}
                                </td>
                                <td class="max-w-[240px] truncate px-4 py-3">
                                    {{ row.product ?? '—' }}
                                </td>
                                <td
                                    class="max-w-[160px] truncate px-4 py-3 font-mono text-xs"
                                >
                                    {{ row.sernum ?? '—' }}
                                </td>
                                <td class="max-w-[280px] truncate px-4 py-3">
                                    {{ row.client_label ?? '—' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ formatDateSell(row.date_sell) }}
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            v-if="total > 0"
            class="flex flex-col gap-2 gap-x-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-sm text-muted-foreground">
                <template v-if="from != null && to != null">
                    Показани {{ from }}–{{ to }} от {{ total }}
                </template>
                <template v-else> Общо {{ total }} записа </template>
            </p>
            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="!canGoPrev()"
                    @click="goToPreviousPage"
                >
                    Предишна
                </Button>
                <span class="text-sm text-muted-foreground">
                    Стр. {{ currentPage }} / {{ lastPage }}
                </span>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="!canGoNext()"
                    @click="goToNextPage"
                >
                    Следваща
                </Button>
            </div>
        </div>
    </div>
</template>
