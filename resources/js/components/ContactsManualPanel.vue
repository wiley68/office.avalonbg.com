<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    MoreHorizontal,
    Plus,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
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

type ContactRow = {
    id: number;
    citi_id: number;
    citi_name?: string | null;
    dlaznosti_id?: number | null;
    dlazhnost_name?: string | null;
    name: string | null;
    second_name: string | null;
    last_name: string;
    firm: string | null;
    email: string | null;
    gsm_1_m: string | null;
    note: string | null;
};

type LookupOption = {
    id: number;
    name: string;
};

type ContactsApiResponse = {
    data?: ContactRow[];
    meta?: {
        current_page?: number;
        per_page?: number;
        total?: number;
        last_page?: number;
        from?: number | null;
        to?: number | null;
    };
};

const API_BASE = '/api/contacts';
const API_CITI = '/api/citi';
const API_DLAZHNOSTI = '/api/dlaznosti';
const LOOKUPS_URL = '/api/contacts/lookups';

const csrfToken = (): string =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

const jsonHeaders = (): HeadersInit => ({
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN': csrfToken(),
});

const contacts = ref<ContactRow[]>([]);
const loading = ref(false);
const listError = ref<string | null>(null);
const searchQuery = ref('');
type SortColumn = 'id' | 'last_name' | 'name' | 'firm';
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
const formCitiId = ref('');
const formDlazhnostId = ref('');
const formName = ref('');
const formSecondName = ref('');
const formLastName = ref('');
const formFirm = ref('');
const formEmail = ref('');
const formGsm = ref('');
const formNote = ref('');

const deleteTarget = ref<ContactRow | null>(null);
const deleteDialogOpen = ref(false);
const deleting = ref(false);
const citiOptions = ref<LookupOption[]>([]);
const dlazhnostiOptions = ref<LookupOption[]>([]);
const citiDialogOpen = ref(false);
const dlazhnostiDialogOpen = ref(false);
const citiLoading = ref(false);
const dlazhnostiLoading = ref(false);
const citiError = ref<string | null>(null);
const dlazhnostiError = ref<string | null>(null);
const citiName = ref('');
const citiPostal = ref('');
const citiEditingId = ref<number | null>(null);
const dlazhnostName = ref('');
const dlazhnostEditingId = ref<number | null>(null);

const textareaClass = cn(
    'min-h-[120px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground',
    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
    'outline-none disabled:cursor-not-allowed disabled:opacity-50',
);

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

const loadContacts = async (page = currentPage.value): Promise<void> => {
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

        const data = (await response.json()) as ContactsApiResponse;
        contacts.value = data.data ?? [];
        currentPage.value = data.meta?.current_page ?? page;
        perPage.value = data.meta?.per_page ?? perPage.value;
        total.value = data.meta?.total ?? contacts.value.length;
        lastPage.value = data.meta?.last_page ?? 1;
        from.value = data.meta?.from ?? null;
        to.value = data.meta?.to ?? null;
    } catch {
        listError.value = 'Неуспешно зареждане на контактите.';
    } finally {
        loading.value = false;
    }
};

const loadLookups = async (): Promise<void> => {
    try {
        const response = await fetch(LOOKUPS_URL, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const data = (await response.json()) as {
            citi?: LookupOption[];
            dlazhnosti?: LookupOption[];
        };
        citiOptions.value = data.citi ?? [];
        dlazhnostiOptions.value = data.dlazhnosti ?? [];
    } catch {
        //
    }
};

const refreshLookups = async (): Promise<void> => {
    await loadLookups();
};

const saveCiti = async (): Promise<void> => {
    const name = citiName.value.trim();
    const postalcod = citiPostal.value.trim();

    if (name.length === 0) {
        citiError.value = 'Името е задължително.';

        return;
    }

    citiError.value = null;
    citiLoading.value = true;

    try {
        const isEdit = citiEditingId.value !== null;
        const url = isEdit ? `${API_CITI}/${citiEditingId.value}` : API_CITI;
        const response = await fetch(url, {
            method: isEdit ? 'PUT' : 'POST',
            headers: jsonHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({
                name,
                postalcod: postalcod || null,
            }),
        });

        if (!response.ok) {
            citiError.value = await parseJsonErrors(response);

            return;
        }

        citiName.value = '';
        citiPostal.value = '';
        citiEditingId.value = null;
        await refreshLookups();
    } catch {
        citiError.value = 'Неуспешен запис на населено място.';
    } finally {
        citiLoading.value = false;
    }
};

const editCiti = (row: LookupOption): void => {
    citiEditingId.value = row.id;
    citiName.value = row.name;
    citiPostal.value = '';
};

const removeCiti = async (id: number): Promise<void> => {
    citiError.value = null;
    citiLoading.value = true;

    try {
        const response = await fetch(`${API_CITI}/${id}`, {
            method: 'DELETE',
            headers: jsonHeaders(),
            credentials: 'same-origin',
        });

        if (!response.ok && response.status !== 204) {
            citiError.value = await parseJsonErrors(response);

            return;
        }

        await refreshLookups();
    } catch {
        citiError.value = 'Неуспешно изтриване на населено място.';
    } finally {
        citiLoading.value = false;
    }
};

const saveDlazhnost = async (): Promise<void> => {
    const name = dlazhnostName.value.trim();

    if (name.length === 0) {
        dlazhnostiError.value = 'Името е задължително.';

        return;
    }

    dlazhnostiError.value = null;
    dlazhnostiLoading.value = true;

    try {
        const isEdit = dlazhnostEditingId.value !== null;
        const url = isEdit
            ? `${API_DLAZHNOSTI}/${dlazhnostEditingId.value}`
            : API_DLAZHNOSTI;
        const response = await fetch(url, {
            method: isEdit ? 'PUT' : 'POST',
            headers: jsonHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ name }),
        });

        if (!response.ok) {
            dlazhnostiError.value = await parseJsonErrors(response);

            return;
        }

        dlazhnostName.value = '';
        dlazhnostEditingId.value = null;
        await refreshLookups();
    } catch {
        dlazhnostiError.value = 'Неуспешен запис на длъжност.';
    } finally {
        dlazhnostiLoading.value = false;
    }
};

const editDlazhnost = (row: LookupOption): void => {
    dlazhnostEditingId.value = row.id;
    dlazhnostName.value = row.name;
};

const removeDlazhnost = async (id: number): Promise<void> => {
    dlazhnostiError.value = null;
    dlazhnostiLoading.value = true;

    try {
        const response = await fetch(`${API_DLAZHNOSTI}/${id}`, {
            method: 'DELETE',
            headers: jsonHeaders(),
            credentials: 'same-origin',
        });

        if (!response.ok && response.status !== 204) {
            dlazhnostiError.value = await parseJsonErrors(response);

            return;
        }

        await refreshLookups();
    } catch {
        dlazhnostiError.value = 'Неуспешно изтриване на длъжност.';
    } finally {
        dlazhnostiLoading.value = false;
    }
};

watch(
    () => props.active,
    (active) => {
        if (active) {
            void loadLookups();
            void loadContacts();
        }
    },
    { immediate: true },
);

const toggleSort = (column: SortColumn): void => {
    if (sortColumn.value !== column) {
        sortColumn.value = column;
        sortDirection.value = 'asc';
        void loadContacts(1);

        return;
    }

    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    void loadContacts(1);
};

const sortIcon = (column: SortColumn) => {
    if (sortColumn.value !== column) {
        return ArrowUpDown;
    }

    return sortDirection.value === 'asc' ? ArrowUp : ArrowDown;
};

watch(searchQuery, () => {
    void loadContacts(1);
});

const resetForm = (): void => {
    editingId.value = null;
    formCitiId.value = '';
    formDlazhnostId.value = '';
    formName.value = '';
    formSecondName.value = '';
    formLastName.value = '';
    formFirm.value = '';
    formEmail.value = '';
    formGsm.value = '';
    formNote.value = '';
    formError.value = null;
};

const openCreate = (): void => {
    resetForm();
    dialogOpen.value = true;
};

const openEdit = (row: ContactRow): void => {
    editingId.value = row.id;
    formCitiId.value = String(row.citi_id);
    formDlazhnostId.value = row.dlaznosti_id ? String(row.dlaznosti_id) : '';
    formName.value = row.name ?? '';
    formSecondName.value = row.second_name ?? '';
    formLastName.value = row.last_name;
    formFirm.value = row.firm ?? '';
    formEmail.value = row.email ?? '';
    formGsm.value = row.gsm_1_m ?? '';
    formNote.value = row.note ?? '';
    formError.value = null;
    dialogOpen.value = true;
};

const submitForm = async (): Promise<void> => {
    formError.value = null;

    const citiId = Number(formCitiId.value);
    const lastName = formLastName.value.trim();

    if (!Number.isInteger(citiId) || citiId <= 0) {
        formError.value = 'citi_id е задължително положително число.';

        return;
    }

    if (lastName.length === 0 || lastName.length > 24) {
        formError.value = 'Фамилията е задължителна и до 24 знака.';

        return;
    }

    saving.value = true;

    try {
        const payload = {
            citi_id: citiId,
            dlaznosti_id: formDlazhnostId.value
                ? Number(formDlazhnostId.value)
                : null,
            last_name: lastName,
            name: formName.value.trim() || null,
            second_name: formSecondName.value.trim() || null,
            firm: formFirm.value.trim() || null,
            email: formEmail.value.trim() || null,
            gsm_1_m: formGsm.value.trim() || null,
            note: formNote.value.trim() || null,
        };

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
        await loadContacts();
    } catch {
        formError.value = 'Неуспешно записване.';
    } finally {
        saving.value = false;
    }
};

const requestDelete = (row: ContactRow): void => {
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
        await loadContacts();
    } catch {
        listError.value = 'Неуспешно изтриване.';
    } finally {
        deleting.value = false;
    }
};

const fullName = (row: ContactRow): string =>
    [row.name, row.second_name, row.last_name].filter(Boolean).join(' ');

const canGoPrev = () => currentPage.value > 1 && !loading.value;
const canGoNext = () => currentPage.value < lastPage.value && !loading.value;

const goToPreviousPage = (): void => {
    if (!canGoPrev()) {
        return;
    }

    void loadContacts(currentPage.value - 1);
};

const goToNextPage = (): void => {
    if (!canGoNext()) {
        return;
    }

    void loadContacts(currentPage.value + 1);
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
                    placeholder="Търсене по име, фирма, имейл или телефон…"
                    autocomplete="off"
                    class="w-full"
                    aria-label="Търсене в контактите"
                />
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    @click="citiDialogOpen = true"
                >
                    Населени места
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    @click="dlazhnostiDialogOpen = true"
                >
                    Длъжности
                </Button>
                <Button type="button" class="shrink-0" @click="openCreate">
                    <Plus class="mr-2 h-4 w-4" />
                    Добави
                </Button>
            </div>
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
                                    />
                                </Button>
                            </th>
                            <th class="px-4 py-3 font-medium">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="-ml-2 h-8 gap-1 px-2 font-medium"
                                    type="button"
                                    @click="toggleSort('last_name')"
                                >
                                    Име
                                    <component
                                        :is="sortIcon('last_name')"
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
                                    @click="toggleSort('firm')"
                                >
                                    Фирма
                                    <component
                                        :is="sortIcon('firm')"
                                        class="h-3.5 w-3.5 opacity-70"
                                    />
                                </Button>
                            </th>
                            <th class="px-4 py-3 font-medium">Имейл</th>
                            <th class="px-4 py-3 font-medium">Телефон</th>
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
                                v-for="row in contacts"
                                :key="row.id"
                                class="border-t border-sidebar-border/60"
                            >
                                <td
                                    class="px-4 py-3 whitespace-nowrap text-muted-foreground tabular-nums"
                                >
                                    {{ row.id }}
                                </td>
                                <td
                                    class="max-w-56 truncate px-4 py-3 font-medium"
                                >
                                    {{ fullName(row) || '—' }}
                                </td>
                                <td
                                    class="max-w-56 truncate px-4 py-3 text-muted-foreground"
                                >
                                    {{ row.firm ?? '—' }}
                                </td>
                                <td
                                    class="max-w-56 truncate px-4 py-3 text-muted-foreground"
                                >
                                    {{ row.email ?? '—' }}
                                </td>
                                <td
                                    class="max-w-40 truncate px-4 py-3 text-muted-foreground"
                                >
                                    {{ row.gsm_1_m ?? '—' }}
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
                            <tr v-if="contacts.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    {{
                                        searchQuery.trim().length > 0
                                            ? 'Няма резултати за това търсене.'
                                            : 'Няма контакти. Добавете първия с бутона „Добави“.'
                                    }}
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <p class="text-muted-foreground">
                Показани {{ from ?? 0 }}-{{ to ?? 0 }} от {{ total }}
            </p>
            <div class="inline-flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="!canGoPrev()"
                    @click="goToPreviousPage"
                >
                    Предишна
                </Button>
                <span class="text-muted-foreground"
                    >Страница {{ currentPage }} / {{ lastPage }}</span
                >
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
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            editingId === null
                                ? 'Нов контакт'
                                : 'Редакция на контакт'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        Минимално задължителни полета: Фамилия, Населено място.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="manual-contact-name">Име</Label>
                        <Input
                            id="manual-contact-name"
                            v-model="formName"
                            maxlength="24"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="manual-contact-second-name">Презиме</Label>
                        <Input
                            id="manual-contact-second-name"
                            v-model="formSecondName"
                            maxlength="24"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="manual-contact-last-name">Фамилия *</Label>
                        <Input
                            id="manual-contact-last-name"
                            v-model="formLastName"
                            maxlength="24"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="manual-contact-citi-id"
                            >Населено място *</Label
                        >
                        <select
                            id="manual-contact-citi-id"
                            v-model="formCitiId"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <option value="">Изберете населено място…</option>
                            <option
                                v-for="city in citiOptions"
                                :key="city.id"
                                :value="String(city.id)"
                            >
                                {{ city.name }} (#{{ city.id }})
                            </option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <Label for="manual-contact-dlazhnost">Длъжност</Label>
                        <select
                            id="manual-contact-dlazhnost"
                            v-model="formDlazhnostId"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <option value="">Без длъжност</option>
                            <option
                                v-for="role in dlazhnostiOptions"
                                :key="role.id"
                                :value="String(role.id)"
                            >
                                {{ role.name }} (#{{ role.id }})
                            </option>
                        </select>
                    </div>
                    <div class="space-y-2 md:col-span-1">
                        <Label for="manual-contact-gsm"
                            >Телефон (gsm_1_m)</Label
                        >
                        <Input
                            id="manual-contact-gsm"
                            v-model="formGsm"
                            maxlength="128"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="manual-contact-email">Имейл</Label>
                        <Input
                            id="manual-contact-email"
                            v-model="formEmail"
                            maxlength="45"
                        />
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <Label for="manual-contact-note">Бележка</Label>
                        <textarea
                            id="manual-contact-note"
                            v-model="formNote"
                            :class="textareaClass"
                            rows="6"
                        />
                    </div>
                </div>

                <p v-if="formError" class="text-sm text-destructive">
                    {{ formError }}
                </p>

                <DialogFooter class="gap-2 sm:gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="saving"
                        @click="dialogOpen = false"
                    >
                        Отказ
                    </Button>
                    <Button
                        type="button"
                        :disabled="saving"
                        @click="submitForm"
                    >
                        {{ saving ? 'Запис…' : 'Запази' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="citiDialogOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Управление на населени места</DialogTitle>
                    <DialogDescription>
                        Добавяне, редакция и изтриване на записи в `citi`.
                    </DialogDescription>
                </DialogHeader>
                <div
                    class="grid grid-cols-1 gap-2 md:grid-cols-[1fr_120px_auto]"
                >
                    <Input
                        v-model="citiName"
                        placeholder="Име на населено място"
                    />
                    <Input
                        v-model="citiPostal"
                        placeholder="ПК"
                        maxlength="4"
                    />
                    <Button
                        type="button"
                        :disabled="citiLoading"
                        @click="saveCiti"
                    >
                        {{ citiEditingId ? 'Обнови' : 'Добави' }}
                    </Button>
                </div>
                <p v-if="citiError" class="text-sm text-destructive">
                    {{ citiError }}
                </p>
                <div class="max-h-[40vh] overflow-auto rounded-md border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th class="px-3 py-2 font-medium">ID</th>
                                <th class="px-3 py-2 font-medium">Име</th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Действия
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in citiOptions"
                                :key="row.id"
                                class="border-t border-sidebar-border/60"
                            >
                                <td class="px-3 py-2">{{ row.id }}</td>
                                <td class="px-3 py-2">{{ row.name }}</td>
                                <td class="px-3 py-2 text-right">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        type="button"
                                        @click="editCiti(row)"
                                        >Ред.</Button
                                    >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        type="button"
                                        class="text-destructive"
                                        @click="removeCiti(row.id)"
                                    >
                                        Изтр.
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="dlazhnostiDialogOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Управление на длъжности</DialogTitle>
                    <DialogDescription>
                        Добавяне, редакция и изтриване на записи в `dlaznosti`.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid grid-cols-1 gap-2 md:grid-cols-[1fr_auto]">
                    <Input
                        v-model="dlazhnostName"
                        placeholder="Име на длъжност"
                    />
                    <Button
                        type="button"
                        :disabled="dlazhnostiLoading"
                        @click="saveDlazhnost"
                    >
                        {{ dlazhnostEditingId ? 'Обнови' : 'Добави' }}
                    </Button>
                </div>
                <p v-if="dlazhnostiError" class="text-sm text-destructive">
                    {{ dlazhnostiError }}
                </p>
                <div class="max-h-[40vh] overflow-auto rounded-md border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th class="px-3 py-2 font-medium">ID</th>
                                <th class="px-3 py-2 font-medium">Име</th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Действия
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in dlazhnostiOptions"
                                :key="row.id"
                                class="border-t border-sidebar-border/60"
                            >
                                <td class="px-3 py-2">{{ row.id }}</td>
                                <td class="px-3 py-2">{{ row.name }}</td>
                                <td class="px-3 py-2 text-right">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        type="button"
                                        @click="editDlazhnost(row)"
                                        >Ред.</Button
                                    >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        type="button"
                                        class="text-destructive"
                                        @click="removeDlazhnost(row.id)"
                                    >
                                        Изтр.
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </DialogContent>
        </Dialog>

        <AlertDialog v-model:open="deleteDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Изтриване на контакт</AlertDialogTitle>
                    <AlertDialogDescription>
                        Сигурни ли сте, че искате да изтриете контакт #{{
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
