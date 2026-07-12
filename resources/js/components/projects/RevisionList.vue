<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
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
import { useAppToast } from '@/composables/useAppToast';
import { useTranslations } from '@/composables/useTranslations';
import {
    destroy as destroyRevision,
    store as storeRevision,
    update as updateRevision,
} from '@/routes/projects/revisions';

export type ProjectRevisionItem = {
    id: number;
    label: string;
    description: string | null;
    sort_order: number;
};

type Props = {
    projectId: number;
    revisions: ProjectRevisionItem[];
};

const props = defineProps<Props>();

const { t } = useTranslations();
const { showError, showMessage } = useAppToast();

const showForm = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const editingRevision = ref<ProjectRevisionItem | null>(null);
const showDeleteDialog = ref(false);
const revisionToDelete = ref<number | null>(null);

const emptyFormState = {
    label: '',
    description: '',
    sort_order: '',
};

const form = useForm({ ...emptyFormState });

const openCreate = (): void => {
    formMode.value = 'create';
    editingRevision.value = null;
    form.defaults({ ...emptyFormState });
    form.reset();
    form.clearErrors();
    showForm.value = true;
};

const openEdit = (revision: ProjectRevisionItem): void => {
    formMode.value = 'edit';
    editingRevision.value = revision;
    form.label = revision.label;
    form.description = revision.description ?? '';
    form.sort_order = String(revision.sort_order);
    showForm.value = true;
};

const closeForm = (): void => {
    showForm.value = false;
};

const submit = (): void => {
    const payload = {
        label: form.label,
        description: form.description || null,
        sort_order: form.sort_order === '' ? null : Number(form.sort_order),
    };

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showMessage(t('common.success'), t('projects.revisions.saved'));
            closeForm();
        },
        onError: (errors: Record<string, string>) => {
            showError(
                t('common.error'),
                Object.values(errors).flat().join('\n'),
            );
        },
    };

    if (formMode.value === 'create') {
        form.transform(() => payload).post(storeRevision(props.projectId).url, options);

        return;
    }

    if (editingRevision.value) {
        form
            .transform(() => payload)
            .put(
                updateRevision({
                    project: props.projectId,
                    revision: editingRevision.value.id,
                }).url,
                options,
            );
    }
};

const requestDelete = (revisionId: number): void => {
    revisionToDelete.value = revisionId;
    showDeleteDialog.value = true;
};

const confirmDelete = (): void => {
    if (revisionToDelete.value === null) {
        return;
    }

    const revisionId = revisionToDelete.value;
    revisionToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(
        destroyRevision({
            project: props.projectId,
            revision: revisionId,
        }).url,
        { preserveScroll: true },
    );
};
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-medium">
                {{ t('projects.revisions.title') }}
            </h3>
            <Button type="button" size="sm" variant="outline" @click="openCreate">
                <Plus class="mr-2 h-4 w-4" />
                {{ t('projects.revisions.add') }}
            </Button>
        </div>

        <div
            v-if="revisions.length === 0"
            class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground"
        >
            {{ t('projects.revisions.empty') }}
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="revision in revisions"
                :key="revision.id"
                class="rounded-lg border p-4"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <p class="font-medium">{{ revision.label }}</p>
                        <p
                            v-if="revision.description"
                            class="text-sm text-muted-foreground"
                        >
                            {{ revision.description }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            @click="openEdit(revision)"
                        >
                            <Pencil class="h-4 w-4" />
                        </Button>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            @click="requestDelete(revision.id)"
                        >
                            <Trash2 class="h-4 w-4 text-destructive" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <Dialog :open="showForm" @update:open="showForm = $event">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {{
                            formMode === 'create'
                                ? t('projects.revisions.create_title')
                                : t('projects.revisions.edit_title')
                        }}
                    </DialogTitle>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="revision-label">{{
                            t('projects.revisions.fields.label')
                        }}</Label>
                        <Input
                            id="revision-label"
                            v-model="form.label"
                            maxlength="80"
                            required
                        />
                        <InputError :message="form.errors.label" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="revision-description">{{
                            t('projects.revisions.fields.description')
                        }}</Label>
                        <textarea
                            id="revision-description"
                            v-model="form.description"
                            rows="3"
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        />
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="revision-sort">{{
                            t('projects.revisions.fields.sort_order')
                        }}</Label>
                        <Input
                            id="revision-sort"
                            v-model="form.sort_order"
                            type="number"
                            min="0"
                        />
                        <InputError :message="form.errors.sort_order" />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="closeForm">
                            {{ t('common.cancel') }}
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ t('common.save') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            :title="t('users.delete_confirm_title')"
            :description="t('projects.revisions.delete_confirm')"
            @confirm="confirmDelete"
            @cancel="revisionToDelete = null; showDeleteDialog = false"
        />
    </div>
</template>
