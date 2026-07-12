<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import DataTable from '@/components/DataTable.vue';
import ProjectFormModal from '@/components/projects/ProjectFormModal.vue';
import { Button } from '@/components/ui/button';
import { useApiTable } from '@/composables/useApiTable';
import { useAppToast } from '@/composables/useAppToast';
import { projectsApiIndex } from '@/composables/useProjectsApiRoute';
import { useTranslations } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { destroy, index, show } from '@/routes/projects';
import type { BreadcrumbItem } from '@/types';
import {
    createProjectColumnTitleMap,
    createProjectColumns,
} from './columns';
import type { ProjectListItem } from './columns';

const { t } = useTranslations();
const { showError } = useAppToast();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('common.dashboard'), href: dashboard() },
    { title: t('projects.title'), href: index() },
]);

const showFormModal = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const editingProject = ref<ProjectListItem | null>(null);
const showDeleteDialog = ref(false);
const projectToDelete = ref<number | null>(null);

const { rows, pagination, loading, search, fetch } =
    useApiTable<ProjectListItem>({
        endpoint: projectsApiIndex().url,
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

const columnTitleMap = computed(() => createProjectColumnTitleMap(t));

const openCreate = (): void => {
    formMode.value = 'create';
    editingProject.value = null;
    showFormModal.value = true;
};

const openEdit = (project: ProjectListItem): void => {
    formMode.value = 'edit';
    editingProject.value = project;
    showFormModal.value = true;
};

const openView = (project: ProjectListItem): void => {
    router.visit(show(project.id).url);
};

const requestDelete = (projectId: number): void => {
    projectToDelete.value = projectId;
    showDeleteDialog.value = true;
};

const columns = computed(() =>
    createProjectColumns({
        t,
        onView: openView,
        onEdit: openEdit,
        onDelete: requestDelete,
    }),
);

const cancelDelete = (): void => {
    projectToDelete.value = null;
    showDeleteDialog.value = false;
};

const confirmDelete = (): void => {
    if (projectToDelete.value === null) {
        return;
    }

    const projectId = projectToDelete.value;
    projectToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(destroy(projectId).url, {
        preserveScroll: true,
        onSuccess: async () => {
            await fetch();

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

const handleSaved = async (): Promise<void> => {
    await fetch();
};

onMounted(async () => {
    await fetch();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('projects.title')" />

        <div class="flex flex-1 flex-col gap-4 overflow-x-auto p-4">
            <div class="flex items-center justify-between">
                <h1 class="grow text-xl font-semibold">
                    {{ t('projects.title') }} ({{ pagination.rowsNumber }})
                </h1>
                <Button @click="openCreate">
                    <Plus class="mr-2 h-4 w-4" />
                    {{ t('projects.add') }}
                </Button>
            </div>

            <div class="flex flex-col rounded-xl border p-4 shadow-sm">
                <DataTable
                    :columns="columns"
                    :data="rows"
                    :loading="loading"
                    :search="search"
                    :column-title-map="columnTitleMap"
                    :search-placeholder="t('projects.search_placeholder')"
                    :empty-message="t('projects.empty')"
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

        <ProjectFormModal
            v-model:open="showFormModal"
            :mode="formMode"
            :project="editingProject"
            @saved="handleSaved"
        />

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            :title="t('users.delete_confirm_title')"
            :description="t('projects.delete_confirm')"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </AppLayout>
</template>
