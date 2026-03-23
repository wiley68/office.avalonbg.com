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
import dashboardRoutes from '@/routes/dashboard';

const props = defineProps<{
    active: boolean;
}>();

type NoteRow = {
    id: number;
    name: string;
    description: string | null;
    note: string;
    created_at: string;
    updated_at: string;
};

const API_BASE = '/api/notes';

const csrfToken = (): string =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

const jsonHeaders = (): HeadersInit => ({
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN': csrfToken(),
});

const notes = ref<NoteRow[]>([]);
const loading = ref(false);
const listError = ref<string | null>(null);
const searchQuery = ref('');
type SortColumn = 'name' | 'updated_at' | 'created_at';
const sortColumn = ref<SortColumn | null>(null);
const sortDirection = ref<'asc' | 'desc'>('desc');
const currentPage = ref(1);
const perPage = ref(12);
const total = ref(0);
const lastPage = ref(1);
const from = ref<number | null>(null);
const to = ref<number | null>(null);

const dialogOpen = ref(false);
const saving = ref(false);
const cryptoProcessing = ref(false);
const formError = ref<string | null>(null);
const editingId = ref<number | null>(null);
const formName = ref('');
const formDescription = ref('');
const formNote = ref('');

const deleteTarget = ref<NoteRow | null>(null);
const deleteDialogOpen = ref(false);
const deleting = ref(false);

const textareaClass = cn(
    'min-h-[140px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground',
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

const tryParseEncryptedPayload = (
    value: string,
): { iv: string; value: string; mac: string } | null => {
    const normalized = value.replace(/\s+/g, '');

    if (normalized.length === 0) {
        return null;
    }

    try {
        const parsed = JSON.parse(atob(normalized)) as
            | {
                  iv?: unknown;
                  value?: unknown;
                  mac?: unknown;
              }
            | null;

        if (
            parsed &&
            typeof parsed.iv === 'string' &&
            typeof parsed.value === 'string' &&
            typeof parsed.mac === 'string'
        ) {
            return {
                iv: parsed.iv,
                value: parsed.value,
                mac: parsed.mac,
            };
        }
    } catch {
        return null;
    }

    return null;
};

const isEncryptedNote = (): boolean => tryParseEncryptedPayload(formNote.value) !== null;

const toggleNoteCrypto = async (): Promise<void> => {
    const text = formNote.value.trim();

    if (text.length === 0 || cryptoProcessing.value) {
        return;
    }

    formError.value = null;
    cryptoProcessing.value = true;

    try {
        const endpoint = isEncryptedNote()
            ? dashboardRoutes.crypto.decrypt.url()
            : dashboardRoutes.crypto.encrypt.url();
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: jsonHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ text: formNote.value }),
        });

        if (!response.ok) {
            formError.value = await parseJsonErrors(response);

            return;
        }

        const data = (await response.json()) as { text?: string };
        formNote.value = data.text ?? formNote.value;
    } catch {
        formError.value = 'Неуспешна операция за криптиране/декриптиране.';
    } finally {
        cryptoProcessing.value = false;
    }
};

type NotesApiResponse = {
    data?: NoteRow[];
    meta?: {
        current_page?: number;
        per_page?: number;
        total?: number;
        last_page?: number;
        from?: number | null;
        to?: number | null;
    };
};

const loadNotes = async (page = currentPage.value): Promise<void> => {
    loading.value = true;
    listError.value = null;

    try {
        const params = new URLSearchParams({
            page: String(page),
            per_page: String(perPage.value),
            sort: sortColumn.value ?? 'updated_at',
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

        const data = (await response.json()) as NotesApiResponse;
        notes.value = data.data ?? [];
        currentPage.value = data.meta?.current_page ?? page;
        perPage.value = data.meta?.per_page ?? perPage.value;
        total.value = data.meta?.total ?? notes.value.length;
        lastPage.value = data.meta?.last_page ?? 1;
        from.value = data.meta?.from ?? null;
        to.value = data.meta?.to ?? null;
    } catch {
        listError.value = 'Неуспешно зареждане на бележките.';
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.active,
    (active) => {
        if (active) {
            void loadNotes();
        }
    },
    { immediate: true },
);

const toggleSort = (column: SortColumn): void => {
    if (sortColumn.value !== column) {
        sortColumn.value = column;
        sortDirection.value = 'asc';

        void loadNotes(1);

        return;
    }

    if (sortDirection.value === 'asc') {
        sortDirection.value = 'desc';

        void loadNotes(1);

        return;
    }

    sortColumn.value = null;
    sortDirection.value = 'desc';
    void loadNotes(1);
};

const sortIcon = (column: SortColumn) => {
    if (sortColumn.value !== column) {
        return ArrowUpDown;
    }

    return sortDirection.value === 'asc' ? ArrowUp : ArrowDown;
};

watch(searchQuery, () => {
    void loadNotes(1);
});

const resetForm = (): void => {
    editingId.value = null;
    formName.value = '';
    formDescription.value = '';
    formNote.value = '';
    formError.value = null;
};

const openCreate = (): void => {
    resetForm();
    dialogOpen.value = true;
};

const openEdit = (row: NoteRow): void => {
    editingId.value = row.id;
    formName.value = row.name;
    formDescription.value = row.description ?? '';
    formNote.value = row.note;
    formError.value = null;
    dialogOpen.value = true;
};

const submitForm = async (): Promise<void> => {
    formError.value = null;
    const name = formName.value.trim();
    const description = formDescription.value.trim();
    const note = formNote.value.trim();

    if (name.length === 0 || name.length > 40) {
        formError.value = 'Името е задължително и до 40 знака.';

        return;
    }

    if (description.length > 120) {
        formError.value = 'Описанието е до 120 знака.';

        return;
    }

    if (note.length === 0) {
        formError.value = 'Съдържанието на бележката е задължително.';

        return;
    }

    saving.value = true;

    try {
        const payload = {
            name,
            description: description.length > 0 ? description : null,
            note,
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
        await loadNotes();
    } catch {
        formError.value = 'Неуспешно записване.';
    } finally {
        saving.value = false;
    }
};

const requestDelete = (row: NoteRow): void => {
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
        await loadNotes();
    } catch {
        listError.value = 'Неуспешно изтриване.';
    } finally {
        deleting.value = false;
    }
};

const formatDate = (iso: string): string => {
    try {
        return new Date(iso).toLocaleString('bg-BG', {
            dateStyle: 'short',
            timeStyle: 'short',
        });
    } catch {
        return iso;
    }
};

const truncate = (text: string, max: number): string => {
    if (text.length <= max) {
        return text;
    }

    return `${text.slice(0, max)}…`;
};

const canGoPrev = () => currentPage.value > 1 && !loading.value;
const canGoNext = () => currentPage.value < lastPage.value && !loading.value;

const goToPreviousPage = (): void => {
    if (!canGoPrev()) {
        return;
    }

    void loadNotes(currentPage.value - 1);
};

const goToNextPage = (): void => {
    if (!canGoNext()) {
        return;
    }

    void loadNotes(currentPage.value + 1);
};
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-auto p-4 md:p-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="w-full min-w-0 sm:max-w-sm">
                <Input
                    v-model="searchQuery"
                    type="search"
                    placeholder="Търсене по име, описание или съдържание…"
                    autocomplete="off"
                    class="w-full"
                    aria-label="Търсене в бележките"
                />
            </div>
            <Button type="button" class="shrink-0" @click="openCreate">
                <Plus class="mr-2 h-4 w-4" />
                Добави
            </Button>
        </div>

        <p v-if="listError" class="text-sm text-destructive">
            {{ listError }}
        </p>

        <div
            class="overflow-hidden rounded-lg border border-sidebar-border/70 bg-background"
        >
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm">
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">ID</th>
                            <th class="px-4 py-3 font-medium">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="-ml-2 h-8 gap-1 px-2 font-medium"
                                    type="button"
                                    @click="toggleSort('name')"
                                >
                                    Име
                                    <component
                                        :is="sortIcon('name')"
                                        class="h-3.5 w-3.5 opacity-70"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </th>
                            <th class="px-4 py-3 font-medium">Описание</th>
                            <th
                                class="hidden px-4 py-3 font-medium md:table-cell"
                            >
                                Бележка
                            </th>
                            <th class="px-4 py-3 font-medium">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="-ml-2 h-8 gap-1 px-2 font-medium"
                                    type="button"
                                    @click="toggleSort('updated_at')"
                                >
                                    Обновена
                                    <component
                                        :is="sortIcon('updated_at')"
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
                                v-for="row in notes"
                                :key="row.id"
                                class="border-t border-sidebar-border/60"
                            >
                                <td
                                    class="px-4 py-3 whitespace-nowrap text-muted-foreground tabular-nums"
                                >
                                    {{ row.id }}
                                </td>
                                <td
                                    class="max-w-48 truncate px-4 py-3 font-medium"
                                >
                                    {{ row.name }}
                                </td>
                                <td
                                    class="max-w-56 truncate px-4 py-3 text-muted-foreground"
                                >
                                    {{ row.description ?? '—' }}
                                </td>
                                <td
                                    class="hidden max-w-xs px-4 py-3 text-muted-foreground md:table-cell"
                                >
                                    {{ truncate(row.note, 50) }}
                                </td>
                                <td
                                    class="px-4 py-3 whitespace-nowrap text-muted-foreground tabular-nums"
                                >
                                    {{ formatDate(row.updated_at) }}
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
                            <tr v-if="notes.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    {{
                                        searchQuery.trim().length > 0
                                            ? 'Няма резултати за това търсене.'
                                            : 'Няма бележки. Добавете първата с бутона „Добави“.'
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
                <span class="text-muted-foreground">
                    Страница {{ currentPage }} / {{ lastPage }}
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
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            editingId === null
                                ? 'Нова бележка'
                                : 'Редакция на бележка'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        Полетата следват същите правила като при агента: име до
                        40 знака, описание до 120 знака, задължително
                        съдържание.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-3">
                    <div class="space-y-2">
                        <Label for="manual-note-name">Име</Label>
                        <Input
                            id="manual-note-name"
                            v-model="formName"
                            maxlength="40"
                            autocomplete="off"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="manual-note-description">Описание</Label>
                        <Input
                            id="manual-note-description"
                            v-model="formDescription"
                            maxlength="120"
                            autocomplete="off"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="manual-note-body">Бележка</Label>
                        <textarea
                            id="manual-note-body"
                            v-model="formNote"
                            :class="textareaClass"
                            rows="8"
                            autocomplete="off"
                        />
                        <div class="flex justify-end">
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="
                                    isEncryptedNote()
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                        : 'bg-muted text-muted-foreground'
                                "
                            >
                                {{
                                    isEncryptedNote()
                                        ? 'Статус: Криптиран'
                                        : 'Статус: Обикновен текст'
                                }}
                            </span>
                        </div>
                    </div>
                    <p v-if="formError" class="text-sm text-destructive">
                        {{ formError }}
                    </p>
                </div>

                <DialogFooter class="gap-2 sm:justify-between">
                    <Button
                        type="button"
                        variant="secondary"
                        :disabled="saving || cryptoProcessing || formNote.trim().length === 0"
                        @click="toggleNoteCrypto"
                    >
                        {{
                            cryptoProcessing
                                ? 'Обработка…'
                                : isEncryptedNote()
                                  ? 'Декриптирай'
                                  : 'Криптирай'
                        }}
                    </Button>
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="saving || cryptoProcessing"
                            @click="dialogOpen = false"
                        >
                            Отказ
                        </Button>
                        <Button
                            type="button"
                            :disabled="saving || cryptoProcessing"
                            @click="submitForm"
                        >
                            {{ saving ? 'Запис…' : 'Запази' }}
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <AlertDialog v-model:open="deleteDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Изтриване на бележка</AlertDialogTitle>
                    <AlertDialogDescription>
                        Сигурни ли сте, че искате да изтриете „{{
                            deleteTarget?.name ?? ''
                        }}“? Това действие не може да бъде отменено.
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
