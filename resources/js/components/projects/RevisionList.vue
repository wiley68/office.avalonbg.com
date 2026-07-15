<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { GripVertical, ListTodo, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import type { SortableEvent } from 'sortablejs';
import { ref, watch } from 'vue';
import draggable from 'vuedraggable';
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
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppToast } from '@/composables/useAppToast';
import { useTranslations } from '@/composables/useTranslations';
import {
    destroy as destroyRevision,
    reorder as reorderRevisions,
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

const emit = defineEmits<{
    viewTasks: [revisionId: number];
}>();

const { t } = useTranslations();
const { showError, showMessage } = useAppToast();

const orderedRevisions = ref<ProjectRevisionItem[]>([]);

watch(
    () => props.revisions,
    (revisions) => {
        orderedRevisions.value = [...revisions];
    },
    { immediate: true },
);

const showForm = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const editingRevision = ref<ProjectRevisionItem | null>(null);
const showDeleteDialog = ref(false);
const revisionToDelete = ref<number | null>(null);

const emptyFormState = {
    label: '',
    description: '',
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
    showForm.value = true;
};

const closeForm = (): void => {
    showForm.value = false;
};

const submit = (): void => {
    const payload = {
        label: form.label,
        description: form.description || null,
    };

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showMessage(t('common.success'), t('projects.stages.saved'));
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

const persistOrder = (revisionIds: number[]): void => {
    router.patch(
        reorderRevisions(props.projectId).url,
        { revision_ids: revisionIds },
        {
            preserveScroll: true,
            onSuccess: () => {
                revisionIds.forEach((revisionId, index) => {
                    const revision = orderedRevisions.value.find(
                        (item) => item.id === revisionId,
                    );

                    if (revision) {
                        revision.sort_order = index;
                    }
                });
            },
            onError: (errors: Record<string, string>) => {
                showError(
                    t('common.error'),
                    Object.values(errors).flat().join('\n'),
                );
                orderedRevisions.value = [...props.revisions];
            },
        },
    );
};

const handleDragEnd = (event: SortableEvent): void => {
    if (event.oldIndex === event.newIndex) {
        return;
    }

    persistOrder(orderedRevisions.value.map((revision) => revision.id));
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
            <div>
                <h3 class="text-sm font-medium">
                    {{ t('projects.stages.title') }}
                </h3>
                <p class="text-xs text-muted-foreground">
                    {{ t('projects.stages.hint') }}
                </p>
                <p
                    v-if="revisions.length > 0"
                    class="text-xs text-muted-foreground"
                >
                    {{ t('projects.stages.drag_hint') }}
                </p>
            </div>
            <Button type="button" size="sm" variant="outline" @click="openCreate">
                <Plus class="mr-2 h-4 w-4" />
                {{ t('projects.stages.add') }}
            </Button>
        </div>

        <div
            v-if="revisions.length === 0"
            class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground"
        >
            {{ t('projects.stages.empty') }}
        </div>

        <draggable
            v-else
            v-model="orderedRevisions"
            item-key="id"
            handle=".revision-drag-handle"
            :animation="180"
            class="space-y-3"
            ghost-class="opacity-50"
            @end="handleDragEnd"
        >
            <template #item="{ element: revision }">
                <div class="rounded-lg border bg-background p-4">
                    <div class="flex items-start gap-2">
                        <button
                            type="button"
                            class="revision-drag-handle mt-0.5 cursor-grab rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground active:cursor-grabbing"
                            :aria-label="t('projects.stages.drag_handle')"
                        >
                            <GripVertical class="h-4 w-4" />
                        </button>

                        <div class="min-w-0 flex-1 space-y-1">
                            <p class="font-medium">{{ revision.label }}</p>
                            <p
                                v-if="revision.description"
                                class="text-sm text-muted-foreground"
                            >
                                {{ revision.description }}
                            </p>
                        </div>

                        <TooltipProvider :delay-duration="200">
                            <div class="flex gap-1">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            :aria-label="t('projects.stages.view_tasks')"
                                            @click="emit('viewTasks', revision.id)"
                                        >
                                            <ListTodo class="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent side="top">
                                        {{ t('projects.stages.view_tasks') }}
                                    </TooltipContent>
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            :aria-label="t('common.edit')"
                                            @click="openEdit(revision)"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent side="top">
                                        {{ t('common.edit') }}
                                    </TooltipContent>
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            :aria-label="t('common.delete')"
                                            @click="requestDelete(revision.id)"
                                        >
                                            <Trash2 class="h-4 w-4 text-destructive" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent side="top">
                                        {{ t('common.delete') }}
                                    </TooltipContent>
                                </Tooltip>
                            </div>
                        </TooltipProvider>
                    </div>
                </div>
            </template>
        </draggable>

        <Dialog :open="showForm" @update:open="showForm = $event">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {{
                            formMode === 'create'
                                ? t('projects.stages.create_title')
                                : t('projects.stages.edit_title')
                        }}
                    </DialogTitle>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="revision-label">{{
                            t('projects.stages.fields.label')
                        }}</Label>
                        <Input
                            id="revision-label"
                            v-model="form.label"
                            maxlength="80"
                            required
                            :placeholder="t('projects.stages.fields.label_placeholder')"
                        />
                        <InputError :message="form.errors.label" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="revision-description">{{
                            t('projects.stages.fields.description')
                        }}</Label>
                        <textarea
                            id="revision-description"
                            v-model="form.description"
                            rows="3"
                            :placeholder="t('projects.stages.fields.description_placeholder')"
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        />
                        <InputError :message="form.errors.description" />
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
            :description="t('projects.stages.delete_confirm')"
            @confirm="confirmDelete"
            @cancel="revisionToDelete = null; showDeleteDialog = false"
        />
    </div>
</template>
