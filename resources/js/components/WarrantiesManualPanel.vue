<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    MoreHorizontal,
    Plus,
} from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';
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

type WarrantyRow = {
    id: number;
    product: string | null;
    sernum: string | null;
    date_sell: string | null;
    client_label: string | null;
};

type WarrantyDetail = {
    id: number;
    client_id: number;
    date_sell: string | null;
    service: string;
    obsluzvane: string;
    product: string | null;
    sernum: string | null;
    invoice: string | null;
    varanty_period: string | null;
    note: string | null;
    motherboard: string | null;
    processor: string | null;
    ram: string | null;
    psu: string | null;
    hdd1: string | null;
    hdd2: string | null;
    dvd: string | null;
    vga: string | null;
    lan: string | null;
    speackers: string | null;
    printer: string | null;
    monitor: string | null;
    kbd: string | null;
    mouse: string | null;
    other: string | null;
    iscomp: string | null;
    motherboardsn: string | null;
    processorsn: string | null;
    ramsn: string | null;
    psusn: string | null;
    hdd1sn: string | null;
    hdd2sn: string | null;
    dvdsn: string | null;
    vgasn: string | null;
    lansn: string | null;
    speackerssn: string | null;
    printersn: string | null;
    monitorsn: string | null;
    kbdsn: string | null;
    mousesn: string | null;
    othersn: string | null;
};

type ContactOption = {
    id: number;
    name: string | null;
    second_name: string | null;
    last_name: string;
    firm: string | null;
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

type ContactsListResponse = {
    data?: ContactOption[];
};

const API_BASE = '/api/warranty-cards';
const CONTACTS_API = '/api/contacts';

const textareaClass = cn(
    'min-h-[100px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground',
    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
    'outline-none disabled:cursor-not-allowed disabled:opacity-50',
);

const jsonHeaders = (): HeadersInit => ({
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN':
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? '',
});

const hwPairs = [
    { label: 'Дънна платка', name: 'motherboard', sn: 'motherboardsn' },
    { label: 'Процесор', name: 'processor', sn: 'processorsn' },
    { label: 'RAM', name: 'ram', sn: 'ramsn' },
    { label: 'Захранване', name: 'psu', sn: 'psusn' },
    { label: 'HDD 1', name: 'hdd1', sn: 'hdd1sn' },
    { label: 'HDD 2', name: 'hdd2', sn: 'hdd2sn' },
    { label: 'DVD / оптика', name: 'dvd', sn: 'dvdsn' },
    { label: 'Видео', name: 'vga', sn: 'vgasn' },
    { label: 'Мрежа', name: 'lan', sn: 'lansn' },
    { label: 'Тонколони', name: 'speackers', sn: 'speackerssn' },
    { label: 'Принтер', name: 'printer', sn: 'printersn' },
    { label: 'Монитор', name: 'monitor', sn: 'monitorsn' },
    { label: 'Клавиатура', name: 'kbd', sn: 'kbdsn' },
    { label: 'Мишка', name: 'mouse', sn: 'mousesn' },
    { label: 'Друго', name: 'other', sn: 'othersn' },
] as const;

function defaultFormState(): Record<string, string> {
    const base: Record<string, string> = {
        client_id: '',
        date_sell_local: '',
        service: 'в сервиз',
        obsluzvane: '4-8',
        product: '',
        sernum: '',
        invoice: '',
        varanty_period: '',
        note: '',
        iscomp: 'No',
    };

    for (const p of hwPairs) {
        base[p.name] = '';
        base[p.sn] = '';
    }

    return base;
}

const form = reactive(defaultFormState());

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

const dialogOpen = ref(false);
const saving = ref(false);
const formError = ref<string | null>(null);
const editingId = ref<number | null>(null);
const loadingDetail = ref(false);

const contactOptions = ref<ContactOption[]>([]);
const contactsLoading = ref(false);

const deleteTarget = ref<WarrantyRow | null>(null);
const deleteDialogOpen = ref(false);
const deleting = ref(false);

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

const dateSellToLocalInput = (value: string | null): string => {
    if (!value) {
        return '';
    }

    const normalized = value.replace(' ', 'T').slice(0, 16);

    return normalized;
};

const localInputToDateSell = (local: string): string => {
    const t = local.trim();

    if (!t) {
        return '';
    }

    if (!t.includes('T')) {
        return t;
    }

    const [datePart, timePart] = t.split('T');

    if (!timePart) {
        return `${datePart} 00:00:00`;
    }

    const time =
        timePart.length === 5 ? `${timePart}:00` : timePart.slice(0, 8);

    return `${datePart} ${time}`;
};

const nullIfEmpty = (s: string): string | null => {
    const t = s.trim();

    return t === '' ? null : t;
};

const contactLabel = (c: ContactOption): string => {
    const name = [c.name, c.second_name, c.last_name].filter(Boolean).join(' ');
    const parts = [c.firm?.trim() || null, name.trim() || null].filter(Boolean);

    return parts.length > 0 ? parts.join(' — ') : `#${c.id}`;
};

const loadContactsForSelect = async (): Promise<void> => {
    if (contactsLoading.value || contactOptions.value.length > 0) {
        return;
    }

    contactsLoading.value = true;

    try {
        const params = new URLSearchParams({
            page: '1',
            per_page: '100',
            sort: 'last_name',
            direction: 'asc',
        });
        const response = await fetch(`${CONTACTS_API}?${params}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const data = (await response.json()) as ContactsListResponse;
        contactOptions.value = data.data ?? [];
    } catch {
        //
    } finally {
        contactsLoading.value = false;
    }
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

watch(dialogOpen, (open) => {
    if (open) {
        void loadContactsForSelect();
    }
});

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

const resetForm = (): void => {
    Object.assign(form, defaultFormState());
    editingId.value = null;
    formError.value = null;
};

const applyDetailToForm = (d: WarrantyDetail): void => {
    form.client_id = String(d.client_id);
    form.date_sell_local = dateSellToLocalInput(d.date_sell);
    form.service = d.service;
    form.obsluzvane = d.obsluzvane;
    form.product = d.product ?? '';
    form.sernum = d.sernum ?? '';
    form.invoice = d.invoice ?? '';
    form.varanty_period = d.varanty_period ?? '';
    form.note = d.note ?? '';
    form.iscomp = d.iscomp ?? 'No';

    for (const p of hwPairs) {
        form[p.name] =
            (d[p.name as keyof WarrantyDetail] as string | null) ?? '';
        form[p.sn] = (d[p.sn as keyof WarrantyDetail] as string | null) ?? '';
    }
};

const openCreate = (): void => {
    resetForm();
    dialogOpen.value = true;
};

const openEdit = async (row: WarrantyRow): Promise<void> => {
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

        const json = (await response.json()) as { data: WarrantyDetail };
        applyDetailToForm(json.data);

        const cid = json.data.client_id;

        if (!contactOptions.value.some((c) => c.id === cid)) {
            const cr = await fetch(`${CONTACTS_API}/${cid}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (cr.ok) {
                const cj = (await cr.json()) as { data: ContactOption };
                contactOptions.value = [cj.data, ...contactOptions.value];
            }
        }
    } catch {
        formError.value = 'Неуспешно зареждане на записа.';
        dialogOpen.value = false;
    } finally {
        loadingDetail.value = false;
    }
};

const buildPayload = (): Record<string, unknown> => {
    const clientId = Number(form.client_id);

    return {
        client_id: clientId,
        date_sell: localInputToDateSell(form.date_sell_local),
        service: form.service,
        obsluzvane: form.obsluzvane,
        product: nullIfEmpty(form.product),
        sernum: nullIfEmpty(form.sernum),
        invoice: nullIfEmpty(form.invoice),
        varanty_period: nullIfEmpty(form.varanty_period),
        note: nullIfEmpty(form.note),
        iscomp: form.iscomp,
        motherboard: nullIfEmpty(form.motherboard),
        processor: nullIfEmpty(form.processor),
        ram: nullIfEmpty(form.ram),
        psu: nullIfEmpty(form.psu),
        hdd1: nullIfEmpty(form.hdd1),
        hdd2: nullIfEmpty(form.hdd2),
        dvd: nullIfEmpty(form.dvd),
        vga: nullIfEmpty(form.vga),
        lan: nullIfEmpty(form.lan),
        speackers: nullIfEmpty(form.speackers),
        printer: nullIfEmpty(form.printer),
        monitor: nullIfEmpty(form.monitor),
        kbd: nullIfEmpty(form.kbd),
        mouse: nullIfEmpty(form.mouse),
        other: nullIfEmpty(form.other),
        motherboardsn: nullIfEmpty(form.motherboardsn),
        processorsn: nullIfEmpty(form.processorsn),
        ramsn: nullIfEmpty(form.ramsn),
        psusn: nullIfEmpty(form.psusn),
        hdd1sn: nullIfEmpty(form.hdd1sn),
        hdd2sn: nullIfEmpty(form.hdd2sn),
        dvdsn: nullIfEmpty(form.dvdsn),
        vgasn: nullIfEmpty(form.vgasn),
        lansn: nullIfEmpty(form.lansn),
        speackerssn: nullIfEmpty(form.speackerssn),
        printersn: nullIfEmpty(form.printersn),
        monitorsn: nullIfEmpty(form.monitorsn),
        kbdsn: nullIfEmpty(form.kbdsn),
        mousesn: nullIfEmpty(form.mousesn),
        othersn: nullIfEmpty(form.othersn),
    };
};

const submitForm = async (): Promise<void> => {
    formError.value = null;

    const clientId = Number(form.client_id);

    if (!Number.isInteger(clientId) || clientId <= 0) {
        formError.value = 'Изберете клиент (контакт).';

        return;
    }

    if (!form.date_sell_local.trim()) {
        formError.value = 'Датата на издаване е задължителна.';

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

        if (response.status === 422) {
            formError.value = await parseJsonErrors(response);

            return;
        }

        if (!response.ok) {
            formError.value = await parseJsonErrors(response);

            return;
        }

        dialogOpen.value = false;
        resetForm();
        await loadWarranties();
    } catch {
        formError.value = 'Неуспешно записване.';
    } finally {
        saving.value = false;
    }
};

const requestDelete = (row: WarrantyRow): void => {
    deleteTarget.value = row;
    deleteDialogOpen.value = true;
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
        await loadWarranties();
    } catch {
        listError.value = 'Неуспешно изтриване.';
    } finally {
        deleting.value = false;
    }
};

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

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';
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
            <Button type="button" class="shrink-0" @click="openCreate">
                <Plus class="mr-2 h-4 w-4" />
                Нова гаранционна карта
            </Button>
        </div>

        <p v-if="listError" class="text-sm text-destructive">
            {{ listError }}
        </p>

        <div
            class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background"
        >
            <div class="overflow-x-auto">
                <table class="w-full min-w-[880px] text-sm">
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
                            <th class="px-4 py-3 text-right font-medium">
                                Управление
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-if="loading">
                            <tr>
                                <td
                                    colspan="6"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    Зареждане…
                                </td>
                            </tr>
                        </template>
                        <template v-else>
                            <tr
                                v-for="row in warranties"
                                :key="row.id"
                                class="border-t border-sidebar-border/60"
                            >
                                <td
                                    class="px-4 py-3 whitespace-nowrap text-muted-foreground tabular-nums"
                                >
                                    {{ row.id }}
                                </td>
                                <td class="max-w-[220px] truncate px-4 py-3">
                                    {{ row.product ?? '—' }}
                                </td>
                                <td
                                    class="max-w-[140px] truncate px-4 py-3 font-mono text-xs"
                                >
                                    {{ row.sernum ?? '—' }}
                                </td>
                                <td class="max-w-[240px] truncate px-4 py-3">
                                    {{ row.client_label ?? '—' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ formatDateSell(row.date_sell) }}
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
                                                <MoreHorizontal
                                                    class="h-4 w-4"
                                                />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
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
                                    {{
                                        searchQuery.trim().length > 0
                                            ? 'Няма резултати за това търсене.'
                                            : 'Няма гаранционни карти. Създайте първата с бутона по-горе.'
                                    }}
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
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            editingId === null
                                ? 'Нова гаранционна карта'
                                : `Редакция на карта #${editingId}`
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        Задължителни: клиент, дата на издаване, тип обслужване,
                        време за реакция. Останалите полета са по избор.
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="loadingDetail"
                    class="py-8 text-center text-sm text-muted-foreground"
                >
                    Зареждане на данни…
                </div>
                <template v-else>
                    <div class="space-y-6">
                        <div>
                            <h3
                                class="mb-3 text-sm font-medium text-foreground"
                            >
                                Основни данни
                            </h3>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div class="space-y-2 md:col-span-2">
                                    <Label for="warranty-client"
                                        >Клиент *</Label
                                    >
                                    <select
                                        id="warranty-client"
                                        v-model="form.client_id"
                                        :class="selectClass"
                                        :disabled="contactsLoading"
                                    >
                                        <option value="">
                                            {{
                                                contactsLoading
                                                    ? 'Зареждане на контакти…'
                                                    : 'Изберете контакт…'
                                            }}
                                        </option>
                                        <option
                                            v-for="c in contactOptions"
                                            :key="c.id"
                                            :value="String(c.id)"
                                        >
                                            {{ contactLabel(c) }} (#{{ c.id }})
                                        </option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <Label for="warranty-date-sell"
                                        >Дата на издаване *</Label
                                    >
                                    <Input
                                        id="warranty-date-sell"
                                        v-model="form.date_sell_local"
                                        type="datetime-local"
                                        autocomplete="off"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label for="warranty-service"
                                        >Тип обслужване *</Label
                                    >
                                    <select
                                        id="warranty-service"
                                        v-model="form.service"
                                        :class="selectClass"
                                    >
                                        <option value="в сервиз">
                                            в сервиз
                                        </option>
                                        <option value="при клиента">
                                            при клиента
                                        </option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <Label for="warranty-obsluzvane"
                                        >Време за реакция *</Label
                                    >
                                    <select
                                        id="warranty-obsluzvane"
                                        v-model="form.obsluzvane"
                                        :class="selectClass"
                                    >
                                        <option value="4-8">4–8</option>
                                        <option value="8-16">8–16</option>
                                        <option value="8-32">8–32</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <Label for="warranty-product"
                                        >Продукт</Label
                                    >
                                    <Input
                                        id="warranty-product"
                                        v-model="form.product"
                                        maxlength="256"
                                        autocomplete="off"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label for="warranty-sernum"
                                        >Сериен номер (карта)</Label
                                    >
                                    <Input
                                        id="warranty-sernum"
                                        v-model="form.sernum"
                                        maxlength="128"
                                        autocomplete="off"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label for="warranty-invoice"
                                        >Фактура</Label
                                    >
                                    <Input
                                        id="warranty-invoice"
                                        v-model="form.invoice"
                                        maxlength="45"
                                        autocomplete="off"
                                    />
                                </div>
                                <div class="space-y-2 md:col-span-2">
                                    <Label for="warranty-period"
                                        >Гаранционен период (описание)</Label
                                    >
                                    <Input
                                        id="warranty-period"
                                        v-model="form.varanty_period"
                                        maxlength="128"
                                        autocomplete="off"
                                    />
                                </div>
                                <div class="space-y-2 md:col-span-2">
                                    <Label for="warranty-note">Бележка</Label>
                                    <textarea
                                        id="warranty-note"
                                        v-model="form.note"
                                        :class="textareaClass"
                                        rows="4"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label for="warranty-iscomp"
                                        >Компютър (изделие)</Label
                                    >
                                    <select
                                        id="warranty-iscomp"
                                        v-model="form.iscomp"
                                        :class="selectClass"
                                    >
                                        <option value="No">Не</option>
                                        <option value="Yes">Да</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3
                                class="mb-3 text-sm font-medium text-foreground"
                            >
                                Конфигурация и серийни номера
                            </h3>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <template v-for="p in hwPairs" :key="p.name">
                                    <div class="space-y-2">
                                        <Label :for="`w-${p.name}`">{{
                                            p.label
                                        }}</Label>
                                        <Input
                                            :id="`w-${p.name}`"
                                            v-model="form[p.name]"
                                            maxlength="128"
                                            autocomplete="off"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <Label :for="`w-${p.sn}`"
                                            >Сериен № ({{
                                                p.label.toLowerCase()
                                            }})</Label
                                        >
                                        <Input
                                            :id="`w-${p.sn}`"
                                            v-model="form[p.sn]"
                                            maxlength="45"
                                            autocomplete="off"
                                        />
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                <p v-if="formError" class="text-sm text-destructive">
                    {{ formError }}
                </p>

                <DialogFooter class="gap-2 sm:gap-2">
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
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <AlertDialog v-model:open="deleteDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle
                        >Изтриване на гаранционна карта</AlertDialogTitle
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
