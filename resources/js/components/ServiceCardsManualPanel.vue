<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    MoreHorizontal,
    Plus,
    X,
} from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';
import ContactSelectCombobox from '@/components/ContactSelectCombobox.vue';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

const props = defineProps<{
    active: boolean;
}>();

type ServiceCardRow = {
    id: number;
    product: string;
    datecard: string | null;
    etap: string;
    contact_label: string | null;
};

type ServiceCardDetail = {
    id: number;
    rakovoditel_id: number | null;
    datecard: string | null;
    name: number;
    special: string;
    product: string;
    varanty: string;
    problem: string | null;
    serviseproblem: string | null;
    serviseproblemtechnik_id: number;
    dopclient: string | null;
    datepredavane: string | null;
    saobshtilclient_id: number;
    clientopisanie: string | null;
    etap: string;
    sold_products?: ServiceCardProductRow[];
};

type ServiceCardProductRow = {
    id: number;
    name: string;
    price: string;
    project_id: number;
    vat: 'Yes' | 'No';
    broi: number;
    ed_cena: string;
};

type ServiceCardsApiResponse = {
    data?: ServiceCardRow[];
    meta?: {
        current_page?: number;
        per_page?: number;
        total?: number;
        last_page?: number;
        from?: number | null;
        to?: number | null;
    };
};

type MemberOption = {
    id: number;
    username: string;
};

const API_BASE = '/api/service-cards';

const textareaClass = cn(
    'min-h-[90px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground',
    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
    'outline-none disabled:cursor-not-allowed disabled:opacity-50',
);

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

const jsonHeaders = (): HeadersInit => ({
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN':
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? '',
});

function defaultFormState(): Record<string, string> {
    return {
        rakovoditel_id: '',
        datecard_local: '',
        name: '',
        special: 'Нормална поръчка',
        product: '',
        varanty: 'Извън гаранционен',
        problem: '',
        serviseproblem: '',
        serviseproblemtechnik_id: '',
        dopclient: '',
        datepredavane_local: '',
        saobshtilclient_id: '',
        clientopisanie: '',
        etap: 'Приета за сервиз',
    };
}

const form = reactive(defaultFormState());
const memberOptions = ref<MemberOption[]>([]);

const warranties = ref<ServiceCardRow[]>([]);
const loading = ref(false);
const listError = ref<string | null>(null);
const searchQuery = ref('');
type SortColumn = 'id' | 'product' | 'datecard' | 'datepredavane' | 'etap';
const sortColumn = ref<SortColumn>('id');
const sortDirection = ref<'asc' | 'desc'>('desc');
const currentPage = ref(1);
const perPage = ref(20);
const total = ref(0);
const lastPage = ref(1);
const from = ref<number | null>(null);
const to = ref<number | null>(null);

const dialogOpen = ref(false);
const saving = ref(false);
const formError = ref<string | null>(null);
const editingId = ref<number | null>(null);
const loadingDetail = ref(false);

const deleteTarget = ref<ServiceCardRow | null>(null);
const deleteDialogOpen = ref(false);
const deleting = ref(false);
const soldProducts = ref<ServiceCardProductRow[]>([]);
const productSaving = ref(false);
const productError = ref<string | null>(null);
const productEditingId = ref<number | null>(null);
const productForm = reactive({
    name: '',
    price: '',
    vat: 'Yes' as 'Yes' | 'No',
    broi: '1',
    ed_cena: '',
});

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

const formatDate = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return value.slice(0, 16).replace('T', ' ');
};

const dateToLocalInput = (value: string | null): string => {
    if (!value) {
        return '';
    }

    return value.replace(' ', 'T').slice(0, 16);
};

const localInputToDate = (local: string): string => {
    const t = local.trim();

    if (!t) {
        return '';
    }

    if (!t.includes('T')) {
        return t;
    }

    const [datePart, timePart] = t.split('T');
    const time =
        !timePart || timePart === '' ? '00:00:00' : `${timePart}:00`.slice(0, 8);

    return `${datePart} ${time}`;
};

const nullIfEmpty = (s: string): string | null => {
    const t = s.trim();

    return t === '' ? null : t;
};

const loadLookups = async (): Promise<void> => {
    const response = await fetch(`${API_BASE}/lookups`, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        return;
    }

    const json = (await response.json()) as { members?: MemberOption[] };
    memberOptions.value = json.members ?? [];
};

const loadCards = async (page = currentPage.value): Promise<void> => {
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

        const data = (await response.json()) as ServiceCardsApiResponse;
        warranties.value = data.data ?? [];
        currentPage.value = data.meta?.current_page ?? page;
        perPage.value = data.meta?.per_page ?? perPage.value;
        total.value = data.meta?.total ?? warranties.value.length;
        lastPage.value = data.meta?.last_page ?? 1;
        from.value = data.meta?.from ?? null;
        to.value = data.meta?.to ?? null;
    } catch {
        listError.value = 'Неуспешно зареждане на сервизните карти.';
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.active,
    async (active) => {
        if (!active) {
            return;
        }

        await loadLookups();
        await loadCards(1);
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

    void loadCards(1);
};

const sortIcon = (column: SortColumn) => {
    if (sortColumn.value !== column) {
        return ArrowUpDown;
    }

    return sortDirection.value === 'asc' ? ArrowUp : ArrowDown;
};

watch(searchQuery, () => {
    void loadCards(1);
});

const resetForm = (): void => {
    Object.assign(form, defaultFormState());
    editingId.value = null;
    formError.value = null;
    soldProducts.value = [];
    resetProductForm();
};

const resetProductForm = (): void => {
    productEditingId.value = null;
    productForm.name = '';
    productForm.price = '';
    productForm.vat = 'Yes';
    productForm.broi = '1';
    productForm.ed_cena = '';
    productError.value = null;
};

const applyDetailToForm = (d: ServiceCardDetail): void => {
    form.rakovoditel_id = d.rakovoditel_id ? String(d.rakovoditel_id) : '';
    form.datecard_local = dateToLocalInput(d.datecard);
    form.name = String(d.name);
    form.special = d.special;
    form.product = d.product;
    form.varanty = d.varanty;
    form.problem = d.problem ?? '';
    form.serviseproblem = d.serviseproblem ?? '';
    form.serviseproblemtechnik_id = String(d.serviseproblemtechnik_id);
    form.dopclient = d.dopclient ?? '';
    form.datepredavane_local = dateToLocalInput(d.datepredavane);
    form.saobshtilclient_id = String(d.saobshtilclient_id);
    form.clientopisanie = d.clientopisanie ?? '';
    form.etap = d.etap;
    soldProducts.value = d.sold_products ?? [];
    resetProductForm();
};

const openCreate = (): void => {
    resetForm();
    dialogOpen.value = true;
};

const openEdit = async (row: ServiceCardRow): Promise<void> => {
    formError.value = null;
    editingId.value = row.id;
    loadingDetail.value = true;
    dialogOpen.value = true;

    try {
        const response = await fetch(`${API_BASE}/${row.id}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            formError.value = await parseJsonErrors(response);
            dialogOpen.value = false;

            return;
        }

        const json = (await response.json()) as { data: ServiceCardDetail };
        applyDetailToForm(json.data);
    } catch {
        formError.value = 'Неуспешно зареждане на записа.';
        dialogOpen.value = false;
    } finally {
        loadingDetail.value = false;
    }
};

const buildPayload = (): Record<string, unknown> => ({
    rakovoditel_id: form.rakovoditel_id ? Number(form.rakovoditel_id) : null,
    datecard: localInputToDate(form.datecard_local),
    name: Number(form.name),
    special: form.special,
    product: form.product.trim(),
    varanty: form.varanty,
    problem: nullIfEmpty(form.problem),
    serviseproblem: nullIfEmpty(form.serviseproblem),
    serviseproblemtechnik_id: Number(form.serviseproblemtechnik_id),
    dopclient: nullIfEmpty(form.dopclient),
    datepredavane: localInputToDate(form.datepredavane_local),
    saobshtilclient_id: Number(form.saobshtilclient_id),
    clientopisanie: nullIfEmpty(form.clientopisanie),
    etap: form.etap,
});

const submitForm = async (): Promise<void> => {
    formError.value = null;

    if (!Number.isInteger(Number(form.name)) || Number(form.name) <= 0) {
        formError.value = 'Изберете клиент.';

        return;
    }

    if (!form.datecard_local.trim() || !form.datepredavane_local.trim()) {
        formError.value = 'Датите са задължителни.';

        return;
    }

    if (!Number.isInteger(Number(form.serviseproblemtechnik_id))) {
        formError.value = 'Изберете техник.';

        return;
    }

    if (!Number.isInteger(Number(form.saobshtilclient_id))) {
        formError.value = 'Изберете служител за уведомяване.';

        return;
    }

    saving.value = true;

    try {
        const payload = buildPayload();
        const isEdit = editingId.value !== null;
        const url = isEdit ? `${API_BASE}/${editingId.value}` : API_BASE;
        const response = await fetch(url, {
            method: isEdit ? 'PUT' : 'POST',
            headers: jsonHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            formError.value = await parseJsonErrors(response);

            return;
        }

        dialogOpen.value = false;
        resetForm();
        await loadCards();
    } catch {
        formError.value = 'Неуспешно записване.';
    } finally {
        saving.value = false;
    }
};

const requestDelete = (row: ServiceCardRow): void => {
    deleteTarget.value = row;
    deleteDialogOpen.value = true;
};

const openPrint = (row: ServiceCardRow): void => {
    window.open(`/dashboard/service-cards/${row.id}/print`, '_blank', 'noopener');
};

const openProductCreate = (): void => {
    resetProductForm();
};

const openProductEdit = (row: ServiceCardProductRow): void => {
    productEditingId.value = row.id;
    productForm.name = row.name;
    productForm.price = String(row.price);
    productForm.vat = row.vat;
    productForm.broi = String(row.broi);
    productForm.ed_cena = String(row.ed_cena);
    productError.value = null;
};

const deleteProduct = async (row: ServiceCardProductRow): Promise<void> => {
    if (editingId.value === null) {
        return;
    }

    const response = await fetch(
        `${API_BASE}/${editingId.value}/products/${row.id}`,
        {
            method: 'DELETE',
            headers: jsonHeaders(),
            credentials: 'same-origin',
        },
    );

    if (!response.ok && response.status !== 204) {
        productError.value = await parseJsonErrors(response);

        return;
    }

    soldProducts.value = soldProducts.value.filter((p) => p.id !== row.id);
};

const saveProduct = async (): Promise<void> => {
    if (editingId.value === null) {
        productError.value = 'Първо запишете сервизната карта.';

        return;
    }

    productSaving.value = true;
    productError.value = null;

    try {
        const payload = {
            name: productForm.name.trim(),
            price: Number(productForm.price),
            vat: productForm.vat,
            broi: Number(productForm.broi),
            ed_cena: Number(productForm.ed_cena),
        };

        const isEdit = productEditingId.value !== null;
        const url = isEdit
            ? `${API_BASE}/${editingId.value}/products/${productEditingId.value}`
            : `${API_BASE}/${editingId.value}/products`;
        const response = await fetch(url, {
            method: isEdit ? 'PUT' : 'POST',
            headers: jsonHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            productError.value = await parseJsonErrors(response);

            return;
        }

        const json = (await response.json()) as { data?: ServiceCardProductRow };
        const saved = json.data;

        if (!saved) {
            return;
        }

        if (isEdit) {
            soldProducts.value = soldProducts.value.map((p) =>
                p.id === saved.id ? saved : p,
            );
        } else {
            soldProducts.value = [...soldProducts.value, saved];
        }

        resetProductForm();
    } catch {
        productError.value = 'Неуспешно записване на продукт.';
    } finally {
        productSaving.value = false;
    }
};

const confirmDelete = async (): Promise<void> => {
    if (!deleteTarget.value) {
        return;
    }

    deleting.value = true;

    try {
        const response = await fetch(`${API_BASE}/${deleteTarget.value.id}`, {
            method: 'DELETE',
            headers: jsonHeaders(),
            credentials: 'same-origin',
        });

        if (!response.ok && response.status !== 204) {
            listError.value = await parseJsonErrors(response);

            return;
        }

        deleteDialogOpen.value = false;
        deleteTarget.value = null;
        await loadCards();
    } catch {
        listError.value = 'Неуспешно изтриване.';
    } finally {
        deleting.value = false;
    }
};

const canGoPrev = () => currentPage.value > 1 && !loading.value;
const canGoNext = () => currentPage.value < lastPage.value && !loading.value;

const goToPreviousPage = (): void => {
    if (canGoPrev()) {
        void loadCards(currentPage.value - 1);
    }
};

const goToNextPage = (): void => {
    if (canGoNext()) {
        void loadCards(currentPage.value + 1);
    }
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
                    placeholder="Търсене по продукт, проблем, клиент или техник…"
                    autocomplete="off"
                    class="w-full"
                />
            </div>
            <Button type="button" class="shrink-0" @click="openCreate">
                <Plus class="mr-2 h-4 w-4" />
                Нова сервизна карта
            </Button>
        </div>

        <p v-if="listError" class="text-sm text-destructive">
            {{ listError }}
        </p>

        <div
            class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background"
        >
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-sm">
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
                                    />
                                </Button>
                            </th>
                            <th class="px-4 py-3 font-medium">Клиент</th>
                            <th class="px-4 py-3 font-medium">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="-ml-2 h-8 gap-1 px-2 font-medium"
                                    type="button"
                                    @click="toggleSort('etap')"
                                >
                                    Етап
                                    <component
                                        :is="sortIcon('etap')"
                                        class="h-3.5 w-3.5 opacity-70"
                                    />
                                </Button>
                            </th>
                            <th class="px-4 py-3 font-medium">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="-ml-2 h-8 gap-1 px-2 font-medium"
                                    type="button"
                                    @click="toggleSort('datecard')"
                                >
                                    Дата
                                    <component
                                        :is="sortIcon('datecard')"
                                        class="h-3.5 w-3.5 opacity-70"
                                    />
                                </Button>
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                Управление
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td
                                colspan="6"
                                class="px-4 py-10 text-center text-muted-foreground"
                            >
                                Зареждане…
                            </td>
                        </tr>
                        <template v-else>
                            <tr
                                v-for="row in warranties"
                                :key="row.id"
                                class="border-t border-sidebar-border/60"
                            >
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ row.id }}
                                </td>
                                <td class="max-w-[240px] truncate px-4 py-3">
                                    {{ row.product }}
                                </td>
                                <td class="max-w-[240px] truncate px-4 py-3">
                                    {{ row.contact_label ?? '—' }}
                                </td>
                                <td class="max-w-[220px] truncate px-4 py-3">
                                    {{ row.etap }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ formatDate(row.datecard) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                type="button"
                                                class="h-8 w-8"
                                            >
                                                <span class="sr-only"
                                                    >Действия</span
                                                >
                                                <MoreHorizontal class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem
                                                class="cursor-pointer"
                                                @select="openPrint(row)"
                                            >
                                                Печат
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                class="cursor-pointer"
                                                @select="openEdit(row)"
                                            >
                                                Редакция
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                class="cursor-pointer text-destructive focus:text-destructive"
                                                @select="requestDelete(row)"
                                            >
                                                Изтриване
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>
                            <tr v-if="warranties.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    Няма сервизни карти.
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

        <Dialog v-model:open="dialogOpen">
            <DialogContent
                :show-close-button="false"
                class="flex max-h-[90vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-5xl"
            >
                <DialogHeader
                    class="sticky top-0 z-20 shrink-0 space-y-0 border-b border-border bg-background px-6 pt-6 pb-4 text-left sm:text-left"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1 space-y-2">
                            <DialogTitle>
                                {{
                                    editingId === null
                                        ? 'Нова сервизна карта'
                                        : `Редакция на карта #${editingId}`
                                }}
                            </DialogTitle>
                            <DialogDescription>
                                Полетата за клиент, дати, продукт, етап, техник и
                                съобщил клиента са задължителни.
                            </DialogDescription>
                        </div>
                        <DialogClose as-child>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="h-9 w-9 shrink-0"
                            >
                                <X class="h-4 w-4" />
                            </Button>
                        </DialogClose>
                    </div>
                </DialogHeader>

                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                    <div
                        v-if="loadingDetail"
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        Зареждане…
                    </div>
                    <template v-else>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label>Клиент *</Label>
                                <ContactSelectCombobox v-model="form.name" />
                            </div>
                            <div class="space-y-2">
                                <Label>Приела сервизната карта (опционално)</Label>
                                <select
                                    v-model="form.rakovoditel_id"
                                    :class="selectClass"
                                >
                                    <option value="">—</option>
                                    <option
                                        v-for="m in memberOptions"
                                        :key="m.id"
                                        :value="String(m.id)"
                                    >
                                        {{ m.username }} (#{{ m.id }})
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label>Дата на приемане *</Label>
                                <Input
                                    v-model="form.datecard_local"
                                    type="datetime-local"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label>Дата предаване *</Label>
                                <Input
                                    v-model="form.datepredavane_local"
                                    type="datetime-local"
                                />
                            </div>
                            <div class="space-y-2 md:col-span-2">
                                <Label>Продукт *</Label>
                                <Input v-model="form.product" maxlength="128" />
                            </div>
                            <div class="space-y-2">
                                <Label>Спешност *</Label>
                                <select v-model="form.special" :class="selectClass">
                                    <option value="Нормална поръчка">
                                        Нормална поръчка
                                    </option>
                                    <option value="Спешна поръчка">
                                        Спешна поръчка
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label>Гаранция *</Label>
                                <select v-model="form.varanty" :class="selectClass">
                                    <option value="Извън гаранционен">
                                        Извън гаранционен
                                    </option>
                                    <option value="Гаранционен">
                                        Гаранционен
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label>Техник (установил проблема) *</Label>
                                <select
                                    v-model="form.serviseproblemtechnik_id"
                                    :class="selectClass"
                                >
                                    <option value="">Изберете…</option>
                                    <option
                                        v-for="m in memberOptions"
                                        :key="m.id"
                                        :value="String(m.id)"
                                    >
                                        {{ m.username }} (#{{ m.id }})
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label>Съобщил клиента *</Label>
                                <select
                                    v-model="form.saobshtilclient_id"
                                    :class="selectClass"
                                >
                                    <option value="">Изберете…</option>
                                    <option
                                        v-for="m in memberOptions"
                                        :key="m.id"
                                        :value="String(m.id)"
                                    >
                                        {{ m.username }} (#{{ m.id }})
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-2 md:col-span-2">
                                <Label>Етап *</Label>
                                <select v-model="form.etap" :class="selectClass">
                                    <option value="Приета за сервиз">
                                        Приета за сервиз
                                    </option>
                                    <option value="Диагностика">
                                        Диагностика
                                    </option>
                                    <option value="Извършва се ремонта">
                                        Извършва се ремонта
                                    </option>
                                    <option value="Изпратен за гаранционен ремонт">
                                        Изпратен за гаранционен ремонт
                                    </option>
                                    <option value="Приключен ремонт">
                                        Приключен ремонт
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label>Проблем приемане</Label>
                                <textarea
                                    v-model="form.problem"
                                    :class="textareaClass"
                                    rows="3"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label>Установен проблем</Label>
                                <textarea
                                    v-model="form.serviseproblem"
                                    :class="textareaClass"
                                    rows="3"
                                />
                            </div>
                            <div class="space-y-2 md:col-span-2">
                                <Label>За клиента</Label>
                                <textarea
                                    v-model="form.dopclient"
                                    :class="textareaClass"
                                    rows="3"
                                />
                            </div>
                            <div class="space-y-2 md:col-span-2">
                                <Label>Клиентско описание</Label>
                                <Input
                                    v-model="form.clientopisanie"
                                    maxlength="512"
                                />
                            </div>
                            <div class="space-y-3 md:col-span-2">
                                <div class="flex items-center justify-between">
                                    <Label class="text-sm font-medium"
                                        >Продадени продукти</Label
                                    >
                                    <Button
                                        v-if="editingId !== null"
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="openProductCreate"
                                    >
                                        Добави продукт
                                    </Button>
                                </div>
                                <p
                                    v-if="editingId === null"
                                    class="text-sm text-muted-foreground"
                                >
                                    Първо запишете сервизната карта, за да
                                    добавяте продадени продукти.
                                </p>
                                <div
                                    v-else
                                    class="overflow-hidden rounded-md border border-border/70"
                                >
                                    <table class="w-full text-sm">
                                        <thead class="bg-muted/40 text-left">
                                            <tr>
                                                <th class="px-3 py-2 font-medium">
                                                    Продукт
                                                </th>
                                                <th class="px-3 py-2 font-medium">
                                                    Кол.
                                                </th>
                                                <th class="px-3 py-2 font-medium">
                                                    Цена
                                                </th>
                                                <th class="px-3 py-2 font-medium">
                                                    Общо
                                                </th>
                                                <th class="px-3 py-2 font-medium">
                                                    ДДС
                                                </th>
                                                <th
                                                    class="px-3 py-2 text-right font-medium"
                                                >
                                                    Действия
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="p in soldProducts"
                                                :key="p.id"
                                                class="border-t border-border/50"
                                            >
                                                <td class="px-3 py-2">
                                                    {{ p.name }}
                                                </td>
                                                <td class="px-3 py-2">
                                                    {{ p.broi }}
                                                </td>
                                                <td class="px-3 py-2">
                                                    {{ p.ed_cena }}
                                                </td>
                                                <td class="px-3 py-2">
                                                    {{ p.price }}
                                                </td>
                                                <td class="px-3 py-2">
                                                    {{
                                                        p.vat === 'Yes'
                                                            ? 'Да'
                                                            : 'Не'
                                                    }}
                                                </td>
                                                <td class="px-3 py-2 text-right">
                                                    <div
                                                        class="inline-flex items-center gap-2"
                                                    >
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="icon"
                                                            class="h-8 w-8"
                                                            title="Редактиране на продукта"
                                                            @click="
                                                                openProductEdit(
                                                                    p,
                                                                )
                                                            "
                                                        >
                                                            <svg
                                                                xmlns="http://www.w3.org/2000/svg"
                                                                viewBox="0 0 24 24"
                                                                class="h-4 w-4"
                                                                aria-hidden="true"
                                                            >
                                                                <title>pencil</title>
                                                                <path
                                                                    d="M20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18,2.9 17.35,2.9 16.96,3.29L15.12,5.12L18.87,8.87M3,17.25V21H6.75L17.81,9.93L14.06,6.18L3,17.25Z"
                                                                />
                                                            </svg>
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="destructive"
                                                            size="icon"
                                                            class="h-8 w-8"
                                                            title="Изтрий продукта"
                                                            @click="
                                                                deleteProduct(p)
                                                            "
                                                        >
                                                            <svg
                                                                xmlns="http://www.w3.org/2000/svg"
                                                                viewBox="0 0 24 24"
                                                                class="h-4 w-4"
                                                                aria-hidden="true"
                                                            >
                                                                <title>delete</title>
                                                                <path
                                                                    d="M19,4H15.5L14.5,3H9.5L8.5,4H5V6H19M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19Z"
                                                                />
                                                            </svg>
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr
                                                v-if="soldProducts.length === 0"
                                                class="border-t border-border/50"
                                            >
                                                <td
                                                    colspan="6"
                                                    class="px-3 py-4 text-center text-muted-foreground"
                                                >
                                                    Няма добавени продукти.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div
                                    v-if="editingId !== null"
                                    class="grid grid-cols-1 gap-2 rounded-md border border-dashed border-border p-3 md:grid-cols-6"
                                >
                                    <Input
                                        v-model="productForm.name"
                                        class="md:col-span-2"
                                        placeholder="Име на продукт"
                                    />
                                    <Input
                                        v-model="productForm.broi"
                                        type="number"
                                        min="1"
                                        placeholder="Брой"
                                    />
                                    <Input
                                        v-model="productForm.ed_cena"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="Ед. цена"
                                    />
                                    <Input
                                        v-model="productForm.price"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="Крайна цена"
                                    />
                                    <select
                                        v-model="productForm.vat"
                                        :class="selectClass"
                                    >
                                        <option value="Yes">ДДС: Да</option>
                                        <option value="No">ДДС: Не</option>
                                    </select>
                                    <div class="md:col-span-6 flex items-center gap-2">
                                        <Button
                                            type="button"
                                            size="sm"
                                            :disabled="productSaving"
                                            @click="saveProduct"
                                        >
                                            {{
                                                productSaving
                                                    ? 'Запис...'
                                                    : productEditingId === null
                                                      ? 'Добави продукт'
                                                      : 'Запази продукта'
                                            }}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            :disabled="productSaving"
                                            @click="resetProductForm"
                                        >
                                            Изчисти
                                        </Button>
                                    </div>
                                    <p
                                        v-if="productError"
                                        class="md:col-span-6 text-sm text-destructive"
                                    >
                                        {{ productError }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div
                    class="sticky bottom-0 z-20 shrink-0 border-t border-border bg-background px-6 py-4"
                >
                    <p v-if="formError" class="mb-3 text-sm text-destructive">
                        {{ formError }}
                    </p>
                    <DialogFooter
                        class="flex flex-row flex-wrap items-center justify-between gap-2 p-0 sm:flex-row"
                    >
                        <div class="flex flex-row items-center gap-2">
                            <Button
                                v-if="editingId !== null"
                                type="button"
                                variant="outline"
                                :disabled="saving || loadingDetail"
                                @click="openPrint({ id: editingId } as ServiceCardRow)"
                            >
                                Печат
                            </Button>
                        </div>
                        <div class="flex flex-row flex-wrap justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="saving || loadingDetail"
                                @click="dialogOpen = false"
                            >
                                Отказ
                            </Button>
                            <Button
                                type="button"
                                :disabled="saving || loadingDetail"
                                @click="submitForm"
                            >
                                {{ saving ? 'Запис…' : 'Запази' }}
                            </Button>
                        </div>
                    </DialogFooter>
                </div>
            </DialogContent>
        </Dialog>

        <AlertDialog v-model:open="deleteDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle
                        >Изтриване на сервизна карта</AlertDialogTitle
                    >
                    <AlertDialogDescription>
                        Сигурни ли сте, че искате да изтриете картата #{{
                            deleteTarget?.id ?? ''
                        }}?
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel :disabled="deleting"
                        >Отказ</AlertDialogCancel
                    >
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="deleting"
                        @click="confirmDelete"
                    >
                        {{ deleting ? 'Изтриване…' : 'Изтрий' }}
                    </Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
