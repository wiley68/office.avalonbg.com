<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FileDown, Loader2, Plus } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import DataTable from '@/components/DataTable.vue';
import EncryptedExportDialog from '@/components/exports/EncryptedExportDialog.vue';
import { Button } from '@/components/ui/button';
import { useApiTable } from '@/composables/useApiTable';
import { useAppToast } from '@/composables/useAppToast';
import { useManageableUsersLabels } from '@/composables/useManageableUsersLabels';
import { useTranslations } from '@/composables/useTranslations';
import { usersApiIndex } from '@/composables/useUsersApiRoute';
import AppLayout from '@/layouts/AppLayout.vue';
import { downloadEncryptedExport } from '@/lib/encryptedExport';
import { dashboard } from '@/routes';
import { create, destroy, exportMethod, index } from '@/routes/users';
import type { BreadcrumbItem, UserRole } from '@/types';
import {
    createUserColumnTitleMap,
    createUserColumns,
} from './columns';
import type { UserListItem } from './columns';

const props = defineProps<{
    manageableRole?: UserRole;
}>();

const labels = useManageableUsersLabels(() => props.manageableRole);
const { t } = useTranslations();
const { showError, showMessage } = useAppToast();

const isExporting = ref(false);
const showExportDialog = ref(false);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('common.dashboard'),
        href: dashboard(),
    },
    {
        title: labels.value.plural,
        href: index(),
    },
]);

const showDeleteDialog = ref(false);
const userToDelete = ref<number | null>(null);

const { rows, pagination, loading, search, fetch } = useApiTable<UserListItem>({
    endpoint: usersApiIndex().url,
    initial: {
        page: 1,
        rowsPerPage: 10,
        sortBy: 'id',
        descending: true,
        search: '',
    },
    onError: (message) => {
        showError(t('common.error'), message);
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

const columnTitleMap = computed(() => createUserColumnTitleMap(t));

const requestDeleteUser = (userId: number): void => {
    userToDelete.value = userId;
    showDeleteDialog.value = true;
};

const columns = computed(() =>
    createUserColumns({
        t,
        onDelete: requestDeleteUser,
    }),
);

const cancelDelete = (): void => {
    userToDelete.value = null;
    showDeleteDialog.value = false;
};

const confirmDelete = (): void => {
    if (userToDelete.value === null) {
        return;
    }

    const userId = userToDelete.value;
    userToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(destroy(userId).url, {
        preserveScroll: true,
        onSuccess: async () => {
            rows.value = rows.value.filter((row) => row.id !== userId);
            pagination.value.rowsNumber = Math.max(
                0,
                pagination.value.rowsNumber - 1,
            );

            if (rows.value.length === 0 && pagination.value.page > 1) {
                pagination.value.page--;
                await fetch();
            }
        },
    });
};

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
        pagination.value.sortBy = 'id';
        pagination.value.descending = true;
    }

    fetch();
};

const updateSearch = (value: string) => {
    search.value = value;
};

const handleExport = async (
    password: string,
    passwordConfirmation: string,
): Promise<void> => {
    isExporting.value = true;

    try {
        const result = await downloadEncryptedExport(
            exportMethod().url,
            password,
            passwordConfirmation,
        );

        if (!result.ok) {
            showError(t('common.error'), result.message);

            return;
        }

        showExportDialog.value = false;
        showMessage(
            t('users.export.button'),
            t('users.export.success', { filename: result.filename }),
        );
    } catch {
        showError(t('common.error'), t('users.export.error'));
    } finally {
        isExporting.value = false;
    }
};

onMounted(async () => {
    await fetch();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="labels.plural" />

        <div class="flex flex-1 flex-col gap-4 overflow-x-auto p-4">
            <div class="flex items-center justify-between">
                <h1 class="grow text-xl font-semibold">
                    {{ labels.plural }} ({{ pagination.rowsNumber }})
                </h1>
                <div class="flex items-center gap-2">
                    <Button
                        variant="secondary"
                        :disabled="isExporting"
                        @click="showExportDialog = true"
                    >
                        <Loader2
                            v-if="isExporting"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <FileDown v-else class="mr-2 h-4 w-4" />
                        {{
                            isExporting
                                ? t('users.export.exporting')
                                : t('users.export.button')
                        }}
                    </Button>
                    <Button as-child>
                        <Link :href="create()">
                            <Plus class="mr-2 h-4 w-4" />
                            {{ labels.add }}
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="flex flex-col rounded-xl border p-4 shadow-sm">
                <DataTable
                    :columns="columns"
                    :data="rows"
                    :loading="loading"
                    :search="search"
                    :column-title-map="columnTitleMap"
                    :search-placeholder="t('users.search_placeholder')"
                    :empty-message="labels.empty"
                    server-side
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
            :title="t('users.delete_confirm_title')"
            :description="labels.deleteConfirm"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />

        <EncryptedExportDialog
            v-model:open="showExportDialog"
            :loading="isExporting"
            i18n-prefix="users.export"
            @confirm="handleExport"
        />
    </AppLayout>
</template>
