<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Plus, Upload } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import DataTable from '@/components/DataTable.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useApiTable } from '@/composables/useApiTable';
import { useAppToast } from '@/composables/useAppToast';
import { documentsApiIndex } from '@/composables/useDocumentsApiRoute';
import { useTranslations } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { destroy, index, store, update } from '@/routes/documents';
import type { BreadcrumbItem } from '@/types';
import {
    createDocumentColumnTitleMap,
    createDocumentColumns,
} from './columns';
import type { DocumentListItem } from './columns';

const { t } = useTranslations();
const { showError } = useAppToast();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('common.dashboard'), href: dashboard() },
    { title: t('documents.title'), href: index() },
]);

const showUploadDialog = ref(false);
const showEditDialog = ref(false);
const editingDocument = ref<DocumentListItem | null>(null);
const editDescription = ref('');
const showDeleteDialog = ref(false);
const documentToDelete = ref<number | null>(null);

const { rows, pagination, loading, search, fetch } =
    useApiTable<DocumentListItem>({
        endpoint: documentsApiIndex().url,
        initial: {
            page: 1,
            rowsPerPage: 10,
            sortBy: 'created_at',
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

const columnTitleMap = computed(() => createDocumentColumnTitleMap(t));

const openEdit = (document: DocumentListItem): void => {
    editingDocument.value = document;
    editDescription.value = document.description ?? '';
    showEditDialog.value = true;
};

const saveEdit = (): void => {
    if (editingDocument.value === null) {
        return;
    }

    router.put(
        update(editingDocument.value.id).url,
        { description: editDescription.value || null },
        {
            preserveScroll: true,
            onSuccess: async () => {
                showEditDialog.value = false;
                await fetch();
            },
        },
    );
};

const requestDelete = (documentId: number): void => {
    documentToDelete.value = documentId;
    showDeleteDialog.value = true;
};

const downloadDocument = (documentId: number): void => {
    window.location.href = `/documents/${documentId}/download`;
};

const columns = computed(() =>
    createDocumentColumns({
        t,
        onEdit: openEdit,
        onDelete: requestDelete,
        onDownload: downloadDocument,
    }),
);

const confirmDelete = (): void => {
    if (documentToDelete.value === null) {
        return;
    }

    const documentId = documentToDelete.value;
    documentToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(destroy(documentId).url, {
        preserveScroll: true,
        onSuccess: async () => {
            await fetch();
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
        pagination.value.sortBy = 'created_at';
        pagination.value.descending = true;
    }

    fetch();
};

const updateSearch = (value: string) => {
    search.value = value;
};

onMounted(async () => {
    await fetch();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('documents.title')" />

        <div class="flex flex-1 flex-col gap-4 overflow-x-auto p-4">
            <div class="flex items-center justify-between">
                <h1 class="grow text-xl font-semibold">
                    {{ t('documents.title') }} ({{ pagination.rowsNumber }})
                </h1>
                <Button @click="showUploadDialog = true">
                    <Upload class="mr-2 h-4 w-4" />
                    {{ t('documents.upload') }}
                </Button>
            </div>

            <div class="flex flex-col rounded-xl border p-4 shadow-sm">
                <DataTable
                    :columns="columns"
                    :data="rows"
                    :loading="loading"
                    :search="search"
                    :column-title-map="columnTitleMap"
                    :search-placeholder="t('documents.search_placeholder')"
                    :empty-message="t('documents.empty')"
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

        <Dialog v-model:open="showUploadDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ t('documents.upload_title') }}</DialogTitle>
                </DialogHeader>

                <Form
                    v-bind="store.form()"
                    :options="{ preserveScroll: true }"
                    reset-on-success
                    class="space-y-4"
                    v-slot="{ errors, processing, recentlySuccessful }"
                    @success="
                        showUploadDialog = false;
                        fetch();
                    "
                >
                    <div class="grid gap-2">
                        <Label for="document-file">{{ t('documents.fields.file') }}</Label>
                        <Input
                            id="document-file"
                            name="file"
                            type="file"
                            required
                        />
                        <InputError :message="errors.file" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="document-description">{{
                            t('documents.fields.description')
                        }}</Label>
                        <textarea
                            id="document-description"
                            name="description"
                            rows="3"
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        />
                        <InputError :message="errors.description" />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showUploadDialog = false"
                        >
                            {{ t('common.cancel') }}
                        </Button>
                        <Button type="submit" :disabled="processing">
                            <Plus class="mr-2 h-4 w-4" />
                            {{ t('documents.upload') }}
                        </Button>
                        <p
                            v-show="recentlySuccessful"
                            class="text-sm text-muted-foreground"
                        >
                            {{ t('documents.uploaded') }}
                        </p>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="showEditDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ t('documents.edit_title') }}</DialogTitle>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="saveEdit">
                    <div class="grid gap-2">
                        <Label for="edit-description">{{
                            t('documents.fields.description')
                        }}</Label>
                        <textarea
                            id="edit-description"
                            v-model="editDescription"
                            rows="3"
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showEditDialog = false"
                        >
                            {{ t('common.cancel') }}
                        </Button>
                        <Button type="submit">
                            {{ t('common.save') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            :title="t('users.delete_confirm_title')"
            :description="t('documents.delete_confirm')"
            @confirm="confirmDelete"
            @cancel="documentToDelete = null; showDeleteDialog = false"
        />
    </AppLayout>
</template>
