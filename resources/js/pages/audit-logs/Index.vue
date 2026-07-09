<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import DataTable from '@/components/DataTable.vue';
import LogExportToolbar from '@/components/logs/LogExportToolbar.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useApiTable } from '@/composables/useApiTable';
import { useTranslations } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import {
    exportMethod,
    destroy,
    destroyBulk,
    index as auditLogsIndex,
} from '@/routes/audit-logs';
import { index as auditLogsApiIndex } from '@/routes/internal/audit-logs';
import type { BreadcrumbItem } from '@/types';
import {
    createAuditLogColumnTitleMap,
    createAuditLogColumns,
    formatAuditDetailValue,
    getAuditFieldLabel,
} from './columns';
import type { AuditLog, AuditLogDetail } from './columns';

const { t } = useTranslations();

const title = computed(() => t('audit_logs.title'));

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('common.dashboard'), href: dashboard() },
    { title: t('nav.logs'), href: auditLogsIndex() },
    { title: title.value, href: auditLogsIndex() },
]);

const showDetailsDialog = ref(false);
const selectedLog = ref<AuditLog | null>(null);
const selectedIds = ref<number[]>([]);
const actionError = ref<string | null>(null);
const showDeleteDialog = ref(false);
const deleteDialogMode = ref<'single' | 'bulk'>('single');
const logToDelete = ref<number | null>(null);

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
    searchDebounceMs: 400,
});

const totalPages = computed(() =>
    Math.max(
        1,
        Math.ceil(pagination.value.rowsNumber / pagination.value.rowsPerPage),
    ),
);

const auditLogColumnTitleMap = computed(() => createAuditLogColumnTitleMap(t));

const isAllSelected = () =>
    rows.value.length > 0 &&
    rows.value.every((row) => selectedIds.value.includes(row.id));

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

const deleteDialogDescription = computed(() => {
    if (deleteDialogMode.value === 'bulk') {
        return t('audit_logs.delete_confirm_bulk', {
            count: String(selectedIds.value.length),
        });
    }

    return t('audit_logs.delete_confirm_single');
});

const requestDeleteLog = (id: number) => {
    logToDelete.value = id;
    deleteDialogMode.value = 'single';
    showDeleteDialog.value = true;
};

const requestDeleteSelected = () => {
    if (selectedIds.value.length === 0) {
        return;
    }

    deleteDialogMode.value = 'bulk';
    showDeleteDialog.value = true;
};

const cancelDelete = () => {
    logToDelete.value = null;
    showDeleteDialog.value = false;
};

const confirmDelete = () => {
    if (deleteDialogMode.value === 'bulk') {
        const ids = [...selectedIds.value];
        showDeleteDialog.value = false;

        router.delete(destroyBulk().url, {
            data: { ids },
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
                fetch();
            },
        });

        return;
    }

    if (logToDelete.value === null) {
        return;
    }

    const id = logToDelete.value;
    logToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(destroy(id).url, {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = selectedIds.value.filter(
                (value) => value !== id,
            );
            fetch();
        },
    });
};

const handleViewDetails = (log: AuditLog) => {
    selectedLog.value = log;
    showDetailsDialog.value = true;
};

const columns = computed(() =>
    createAuditLogColumns({
        t,
        onViewDetails: handleViewDetails,
        onDelete: requestDeleteLog,
        isRowSelected: (id) => selectedIds.value.includes(id),
        onToggleRow: toggleSelectRow,
        isAllSelected,
        onToggleAll: toggleSelectAll,
    }),
);

const handlePaginationChange = (page: number, pageSize: number) => {
    pagination.value.page = page;
    pagination.value.rowsPerPage = pageSize;
    fetch();
};

const handleSortingChange = (sorting: { id: string; desc: boolean }[]) => {
    if (sorting.length > 0) {
        pagination.value.sortBy = sorting[0].id;
        pagination.value.descending = sorting[0].desc;
    } else {
        pagination.value.sortBy = 'occurred_at';
        pagination.value.descending = true;
    }

    fetch();
};

const updateSearch = (value: string) => {
    search.value = value;
};

const hasChangeValues = (detail: AuditLogDetail) =>
    detail.начална_стойност !== undefined ||
    detail.крайна_стойност !== undefined;

onMounted(async () => {
    await fetch();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="title" />

        <div class="flex flex-1 flex-col gap-4 overflow-x-auto p-4">
            <div class="flex items-center justify-end">
                <div class="flex w-full items-center gap-2">
                    <h2 class="grow text-xl font-medium">
                        {{ title }} ({{ pagination.rowsNumber }})
                    </h2>
                    <LogExportToolbar :export-url="exportMethod().url" />
                </div>
            </div>

            <div class="flex flex-col rounded-xl border p-4 shadow-sm">
                <div
                    v-if="selectedIds.length > 0"
                    class="mb-3 flex items-center gap-2"
                >
                    <Button
                        variant="destructive"
                        size="sm"
                        type="button"
                        @click="requestDeleteSelected"
                    >
                        <Trash2 class="mr-1 h-4 w-4" />
                        {{
                            t('audit_logs.delete_selected', {
                                count: String(selectedIds.length),
                            })
                        }}
                    </Button>
                </div>

                <p v-if="actionError" class="mb-3 text-sm text-destructive">
                    {{ actionError }}
                </p>

                <DataTable
                    :columns="columns"
                    :data="rows"
                    :loading="loading"
                    :search="search"
                    :column-title-map="auditLogColumnTitleMap"
                    :search-placeholder="t('audit_logs.search_placeholder')"
                    :empty-message="t('audit_logs.empty')"
                    :loading-message="t('audit_logs.loading')"
                    :show-pagination="true"
                    :show-column-toggle="true"
                    :page-size="pagination.rowsPerPage"
                    :current-page="pagination.page"
                    :total-pages="totalPages"
                    :total-items="pagination.rowsNumber"
                    @search-change="updateSearch"
                    @pagination-change="handlePaginationChange"
                    @sorting-change="handleSortingChange"
                />
            </div>
        </div>

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            :title="t('audit_logs.delete_confirm_title')"
            :description="deleteDialogDescription"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />

        <Dialog
            :open="showDetailsDialog"
            @update:open="(open: boolean) => (showDetailsDialog = open)"
        >
            <DialogContent class="max-h-[85vh] max-w-3xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{{ t('audit_logs.details_title') }}</DialogTitle>
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
                            <span class="text-muted-foreground"
                                >{{ t('audit_logs.result') }}:</span
                            >
                            {{
                                selectedLog.is_success
                                    ? t('audit_logs.success')
                                    : t('audit_logs.failure')
                            }}
                        </div>
                        <div class="truncate" :title="selectedLog.user_email">
                            <span class="text-muted-foreground"
                                >{{ t('common.email') }}:</span
                            >
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
                                        {{ t('audit_logs.field') }}
                                    </th>
                                    <th
                                        v-if="
                                            selectedLog.details.some(
                                                hasChangeValues,
                                            )
                                        "
                                        class="px-3 py-2 text-left font-medium"
                                    >
                                        {{ t('audit_logs.initial_value') }}
                                    </th>
                                    <th
                                        v-if="
                                            selectedLog.details.some(
                                                hasChangeValues,
                                            )
                                        "
                                        class="px-3 py-2 text-left font-medium"
                                    >
                                        {{ t('audit_logs.final_value') }}
                                    </th>
                                    <th
                                        v-if="
                                            !selectedLog.details.some(
                                                hasChangeValues,
                                            )
                                        "
                                        class="px-3 py-2 text-left font-medium"
                                    >
                                        {{ t('audit_logs.value') }}
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
                                            getAuditFieldLabel(t, detail.поле)
                                        }}
                                    </td>
                                    <template v-if="hasChangeValues(detail)">
                                        <td
                                            class="px-3 py-2 align-top break-all"
                                        >
                                            {{
                                                formatAuditDetailValue(
                                                    t,
                                                    detail.поле,
                                                    detail.начална_стойност,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="px-3 py-2 align-top break-all"
                                        >
                                            {{
                                                formatAuditDetailValue(
                                                    t,
                                                    detail.поле,
                                                    detail.крайна_стойност,
                                                )
                                            }}
                                        </td>
                                    </template>
                                    <td
                                        v-else
                                        class="px-3 py-2 align-top break-all"
                                    >
                                        {{
                                            formatAuditDetailValue(
                                                t,
                                                detail.поле,
                                                detail.стойност,
                                            )
                                        }}
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
