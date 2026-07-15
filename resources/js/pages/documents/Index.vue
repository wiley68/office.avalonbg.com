<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Loader2, Plus, Upload } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import DocumentCard from '@/components/documents/DocumentCard.vue';
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
import { useApiLoadMore } from '@/composables/useApiLoadMore';
import { useAppToast } from '@/composables/useAppToast';
import { documentsApiIndex } from '@/composables/useDocumentsApiRoute';
import { useTranslations } from '@/composables/useTranslations';
import { openDocument, saveDocumentLocally } from '@/lib/openDocument';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { destroy, index, replaceFile, store, update } from '@/routes/documents';
import type { BreadcrumbItem } from '@/types';
import type { DocumentListItem } from './columns';

const { t } = useTranslations();
const { showError } = useAppToast();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('common.dashboard'), href: dashboard() },
    { title: t('documents.title'), href: index() },
]);

const showUploadDialog = ref(false);
const showEditDialog = ref(false);
const showReplaceDialog = ref(false);
const editingDocument = ref<DocumentListItem | null>(null);
const replacingDocument = ref<DocumentListItem | null>(null);
const editDescription = ref('');
const showDeleteDialog = ref(false);
const documentToDelete = ref<number | null>(null);

const {
    rows,
    loading,
    loadingMore,
    search,
    total,
    hasMore,
    fetch,
    loadMore,
} = useApiLoadMore<DocumentListItem>({
    endpoint: documentsApiIndex().url,
    perPage: 12,
    sortBy: 'created_at',
    sortDesc: true,
    onError: (message) => {
        showError(t('common.error'), message);
    },
    autoload: false,
});

const openEdit = (document: DocumentListItem): void => {
    editingDocument.value = document;
    editDescription.value = document.description ?? '';
    showEditDialog.value = true;
};

const openReplace = (document: DocumentListItem): void => {
    replacingDocument.value = document;
    showReplaceDialog.value = true;
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

const viewDocument = (documentId: number): void => {
    openDocument(documentId);
};

const downloadDocument = (documentId: number): void => {
    saveDocumentLocally(documentId);
};

const cancelDelete = (): void => {
    documentToDelete.value = null;
    showDeleteDialog.value = false;
};

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
        onError: (errors) => {
            showError(t('common.error'), Object.values(errors).flat().join('\n'));
        },
    });
};

onMounted(async () => {
    await fetch();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('documents.title')" />

        <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-hidden p-4">
            <div
                class="flex shrink-0 flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <h1 class="text-xl font-semibold">
                    {{ t('documents.title') }} ({{ total }})
                </h1>
                <div
                    class="flex w-full flex-col gap-4 sm:w-auto sm:flex-row sm:items-center"
                >
                    <Input
                        v-model="search"
                        type="search"
                        :placeholder="t('documents.search_placeholder')"
                        class="w-full sm:max-w-md"
                    />
                    <Button
                        class="shrink-0 self-start sm:self-auto"
                        @click="showUploadDialog = true"
                    >
                        <Upload class="mr-2 h-4 w-4" />
                        {{ t('documents.upload') }}
                    </Button>
                </div>
            </div>

            <div
                class="min-h-0 flex-1 overflow-y-auto rounded-xl border p-4 shadow-sm"
            >
                <div
                    v-if="loading"
                    class="flex items-center justify-center gap-2 py-16 text-muted-foreground"
                >
                    <Loader2 class="size-5 animate-spin" />
                    {{ t('common.table.loading') }}
                </div>

                <p
                    v-else-if="rows.length === 0"
                    class="py-16 text-center text-muted-foreground"
                >
                    {{ t('documents.empty') }}
                </p>

                <template v-else>
                    <div
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6"
                    >
                        <DocumentCard
                            v-for="document in rows"
                            :key="document.id"
                            :document="document"
                            @edit="openEdit"
                            @delete="requestDelete"
                            @view="viewDocument"
                            @download="downloadDocument"
                            @replace="openReplace"
                        />
                    </div>

                    <div
                        v-if="hasMore"
                        class="flex justify-center pt-4"
                    >
                        <Button
                            variant="outline"
                            class="min-w-40"
                            :disabled="loadingMore"
                            @click="loadMore"
                        >
                            <Loader2
                                v-if="loadingMore"
                                class="mr-2 size-4 animate-spin"
                            />
                            {{ t('documents.show_more') }}
                        </Button>
                    </div>
                </template>
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
                        <Label for="document-file">{{
                            t('documents.fields.file')
                        }}</Label>
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

        <Dialog v-model:open="showReplaceDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ t('documents.replace_title') }}</DialogTitle>
                </DialogHeader>

                <Form
                    v-if="replacingDocument"
                    v-bind="replaceFile.form(replacingDocument.id)"
                    :options="{ preserveScroll: true }"
                    reset-on-success
                    class="space-y-4"
                    v-slot="{ errors, processing, recentlySuccessful }"
                    @success="
                        showReplaceDialog = false;
                        replacingDocument = null;
                        fetch();
                    "
                >
                    <p class="text-sm text-muted-foreground">
                        {{ t('documents.replace_hint', { name: replacingDocument.original_name }) }}
                    </p>

                    <div class="grid gap-2">
                        <Label for="replace-document-file">{{
                            t('documents.fields.file')
                        }}</Label>
                        <Input
                            id="replace-document-file"
                            name="file"
                            type="file"
                            required
                        />
                        <InputError :message="errors.file" />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="
                                showReplaceDialog = false;
                                replacingDocument = null;
                            "
                        >
                            {{ t('common.cancel') }}
                        </Button>
                        <Button type="submit" :disabled="processing">
                            <Upload class="mr-2 h-4 w-4" />
                            {{ t('documents.replace') }}
                        </Button>
                        <p
                            v-show="recentlySuccessful"
                            class="text-sm text-muted-foreground"
                        >
                            {{ t('documents.replaced') }}
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
            @cancel="cancelDelete"
        />
    </AppLayout>
</template>
