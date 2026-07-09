<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Eye,
    Loader2,
    Trash2,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import LogExportToolbar from '@/components/logs/LogExportToolbar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useApiTable } from '@/composables/useApiTable';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { exportMethod, destroy, destroyBulk, index as auditLogsIndex } from '@/routes/audit-logs';
import { index as auditLogsApiIndex } from '@/routes/internal/audit-logs';
import type { BreadcrumbItem } from '@/types';

export interface AuditLogDetail {
    поле: string;
    стойност?: string | null;
    начална_стойност?: string | null;
    крайна_стойност?: string | null;
}

export interface AuditLog {
    id: number;
    occurred_at: string;
    event_type:
        | 'login_success'
        | 'login_failed'
        | 'two_factor_challenge_success'
        | 'two_factor_challenge_failed';
    event_type_label: string;
    event_source: 'office' | 'api';
    event_source_label: string;
    is_success: boolean;
    user_id: number | null;
    user_email: string;
    user_name: string;
    details: AuditLogDetail[];
    details_count: number;
}

const auditFieldLabelMap: Record<string, string> = {
    имейл: 'Имейл',
    причина: 'Причина',
};

const title = 'Одит';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Табло', href: dashboard() },
    { title: 'Журнали', href: auditLogsIndex() },
    { title, href: auditLogsIndex() },
];

const showDetailsDialog = ref(false);
const selectedLog = ref<AuditLog | null>(null);
const selectedIds = ref<number[]>([]);
const actionError = ref<string | null>(null);

const { rows, pagination, loading, search, fetch } = useApiTable<AuditLog>({
    endpoint: auditLogsApiIndex().url,
    initial: {
        page: 1,
        rowsPerPage: 10,
        sortBy: 'occurred_at',
        descending: true,
        search: '',
    },
    onError: (message) => {
        actionError.value = message;
    },
    autoload: false,
});

const totalPages = computed(() =>
    Math.max(1, Math.ceil(pagination.value.rowsNumber / pagination.value.rowsPerPage)),
);

const allVisibleSelected = computed(
    () =>
        rows.value.length > 0 &&
        rows.value.every((row) => selectedIds.value.includes(row.id)),
);

const eventTypeVariant = (type: AuditLog['event_type']) => {
    switch (type) {
        case 'login_success':
        case 'two_factor_challenge_success':
            return 'default';
        case 'login_failed':
        case 'two_factor_challenge_failed':
            return 'destructive';
        default:
            return 'outline';
    }
};

const toggleSort = (column: string) => {
    if (pagination.value.sortBy === column) {
        pagination.value.descending = !pagination.value.descending;
    } else {
        pagination.value.sortBy = column;
        pagination.value.descending = true;
    }

    fetch();
};

const sortIcon = (column: string) => {
    if (pagination.value.sortBy !== column) {
        return ArrowUpDown;
    }

    return pagination.value.descending ? ArrowDown : ArrowUp;
};

const changePage = (page: number) => {
    pagination.value.page = page;
    fetch();
};

const changePageSize = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    pagination.value.rowsPerPage = Number(target.value);
    pagination.value.page = 1;
    fetch();
};

const handleViewDetails = (log: AuditLog) => {
    selectedLog.value = log;
    showDetailsDialog.value = true;
};

const formatValue = (value: string | null | undefined) => {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return value;
};

const hasChangeValues = (detail: AuditLogDetail) =>
    detail.начална_стойност !== undefined ||
    detail.крайна_стойност !== undefined;

const toggleSelectAll = (checked: boolean) => {
    if (checked) {
        selectedIds.value = rows.value.map((row) => row.id);

        return;
    }

    selectedIds.value = [];
};

const toggleSelectRow = (id: number, checked: boolean) => {
    if (checked) {
        if (!selectedIds.value.includes(id)) {
            selectedIds.value.push(id);
        }

        return;
    }

    selectedIds.value = selectedIds.value.filter((value) => value !== id);
};

const deleteLog = (id: number) => {
    if (!window.confirm('Сигурни ли сте, че искате да изтриете този запис?')) {
        return;
    }

    router.delete(destroy(id).url, {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = selectedIds.value.filter((value) => value !== id);
            fetch();
        },
    });
};

const deleteSelected = () => {
    if (selectedIds.value.length === 0) {
        return;
    }

    if (
        !window.confirm(
            `Сигурни ли сте, че искате да изтриете ${selectedIds.value.length} избрани записа?`,
        )
    ) {
        return;
    }

    router.delete(destroyBulk().url, {
        data: { ids: selectedIds.value },
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
            fetch();
        },
    });
};

onMounted(async () => {
    await fetch();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="title" />

        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-xl font-semibold">
                        {{ title }} ({{ pagination.rowsNumber }})
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Записи за опити за вход в системата.
                    </p>
                </div>
                <LogExportToolbar :export-url="exportMethod().url" />
            </div>

            <div
                class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4 shadow-sm"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <Input
                        v-model="search"
                        placeholder="Търси по потребител, имейл, събитие..."
                        class="max-w-sm"
                    />
                    <Button
                        v-if="selectedIds.length > 0"
                        variant="destructive"
                        size="sm"
                        type="button"
                        @click="deleteSelected"
                    >
                        <Trash2 class="mr-1 h-4 w-4" />
                        Изтрий избраните ({{ selectedIds.length }})
                    </Button>
                </div>

                <p v-if="actionError" class="text-sm text-destructive">
                    {{ actionError }}
                </p>

                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="w-10 px-3 py-3">
                                    <Checkbox
                                        :model-value="allVisibleSelected"
                                        @update:model-value="
                                            (value) =>
                                                toggleSelectAll(Boolean(value))
                                        "
                                    />
                                </th>
                                <th
                                    v-for="column in [
                                        { key: 'id', label: '№' },
                                        { key: 'occurred_at', label: 'Дата' },
                                        { key: 'event_type', label: 'Събитие' },
                                        {
                                            key: 'event_source',
                                            label: 'Източник',
                                        },
                                        { key: 'is_success', label: 'Резултат' },
                                        {
                                            key: 'user_name',
                                            label: 'Потребител',
                                        },
                                        { key: 'user_email', label: 'Имейл' },
                                    ]"
                                    :key="column.key"
                                    class="px-3 py-3 font-medium"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 hover:text-foreground"
                                        @click="toggleSort(column.key)"
                                    >
                                        {{ column.label }}
                                        <component
                                            :is="sortIcon(column.key)"
                                            class="h-4 w-4 text-muted-foreground"
                                        />
                                    </button>
                                </th>
                                <th class="px-3 py-3 font-medium">Детайли</th>
                                <th class="px-3 py-3 font-medium">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td
                                    colspan="10"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    <Loader2
                                        class="mx-auto mb-2 h-5 w-5 animate-spin"
                                    />
                                    Данните се зареждат...
                                </td>
                            </tr>
                            <tr v-else-if="rows.length === 0">
                                <td
                                    colspan="10"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    Няма съответстващи записи
                                </td>
                            </tr>
                            <tr
                                v-for="log in rows"
                                v-else
                                :key="log.id"
                                class="border-t border-sidebar-border/60"
                            >
                                <td class="px-3 py-3">
                                    <Checkbox
                                        :model-value="
                                            selectedIds.includes(log.id)
                                        "
                                        @update:model-value="
                                            (value) =>
                                                toggleSelectRow(
                                                    log.id,
                                                    Boolean(value),
                                                )
                                        "
                                    />
                                </td>
                                <td class="px-3 py-3 font-medium">
                                    {{ log.id }}
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    {{ log.occurred_at }}
                                </td>
                                <td class="px-3 py-3">
                                    <Badge
                                        :variant="eventTypeVariant(log.event_type)"
                                    >
                                        {{ log.event_type_label }}
                                    </Badge>
                                </td>
                                <td class="px-3 py-3">
                                    {{ log.event_source_label }}
                                </td>
                                <td class="px-3 py-3">
                                    <Badge
                                        :variant="
                                            log.is_success
                                                ? 'default'
                                                : 'destructive'
                                        "
                                    >
                                        {{
                                            log.is_success ? 'Успех' : 'Неуспех'
                                        }}
                                    </Badge>
                                </td>
                                <td class="px-3 py-3">{{ log.user_name }}</td>
                                <td
                                    class="max-w-[220px] truncate px-3 py-3"
                                    :title="log.user_email"
                                >
                                    {{ log.user_email }}
                                </td>
                                <td class="px-3 py-3">
                                    {{ log.details_count }}
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            type="button"
                                            @click="handleViewDetails(log)"
                                        >
                                            <Eye class="mr-1 h-4 w-4" />
                                            Преглед
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            type="button"
                                            @click="deleteLog(log.id)"
                                        >
                                            <Trash2 class="mr-1 h-4 w-4" />
                                            Изтрий
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground"
                >
                    <div class="flex items-center gap-2">
                        <span>Редове на страница</span>
                        <select
                            class="rounded-md border bg-background px-2 py-1"
                            :value="pagination.rowsPerPage"
                            @change="changePageSize"
                        >
                            <option :value="10">10</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            type="button"
                            :disabled="pagination.page <= 1 || loading"
                            @click="changePage(pagination.page - 1)"
                        >
                            Предишна
                        </Button>
                        <span>
                            Страница {{ pagination.page }} от {{ totalPages }}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            type="button"
                            :disabled="pagination.page >= totalPages || loading"
                            @click="changePage(pagination.page + 1)"
                        >
                            Следваща
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <Dialog
            :open="showDetailsDialog"
            @update:open="(open: boolean) => (showDetailsDialog = open)"
        >
            <DialogContent class="max-h-[85vh] max-w-3xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Детайли на събитието</DialogTitle>
                    <DialogDescription v-if="selectedLog">
                        {{ selectedLog.event_type_label }} ·
                        {{ selectedLog.occurred_at }} ·
                        {{ selectedLog.user_name }} ·
                        {{ selectedLog.event_source_label }}
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedLog" class="space-y-3">
                    <div class="grid gap-2 text-sm sm:grid-cols-2">
                        <div>
                            <span class="text-muted-foreground">Резултат:</span>
                            {{
                                selectedLog.is_success ? 'Успех' : 'Неуспех'
                            }}
                        </div>
                        <div class="truncate" :title="selectedLog.user_email">
                            <span class="text-muted-foreground">Имейл:</span>
                            {{ selectedLog.user_email }}
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-md border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th
                                        class="px-3 py-2 text-left font-medium"
                                    >
                                        Поле
                                    </th>
                                    <th
                                        v-if="
                                            selectedLog.details.some(
                                                hasChangeValues,
                                            )
                                        "
                                        class="px-3 py-2 text-left font-medium"
                                    >
                                        Начална стойност
                                    </th>
                                    <th
                                        v-if="
                                            selectedLog.details.some(
                                                hasChangeValues,
                                            )
                                        "
                                        class="px-3 py-2 text-left font-medium"
                                    >
                                        Крайна стойност
                                    </th>
                                    <th
                                        v-if="
                                            !selectedLog.details.some(
                                                hasChangeValues,
                                            )
                                        "
                                        class="px-3 py-2 text-left font-medium"
                                    >
                                        Стойност
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(detail, index) in selectedLog.details"
                                    :key="`${selectedLog.id}-${index}`"
                                    class="border-t"
                                >
                                    <td class="px-3 py-2 align-top">
                                        {{
                                            auditFieldLabelMap[detail.поле] ??
                                            detail.поле
                                        }}
                                    </td>
                                    <template v-if="hasChangeValues(detail)">
                                        <td class="px-3 py-2 align-top break-all">
                                            {{
                                                formatValue(
                                                    detail.начална_стойност,
                                                )
                                            }}
                                        </td>
                                        <td class="px-3 py-2 align-top break-all">
                                            {{
                                                formatValue(
                                                    detail.крайна_стойност,
                                                )
                                            }}
                                        </td>
                                    </template>
                                    <td
                                        v-else
                                        class="px-3 py-2 align-top break-all"
                                    >
                                        {{ formatValue(detail.стойност) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
