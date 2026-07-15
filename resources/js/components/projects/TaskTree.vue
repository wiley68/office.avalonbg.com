<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed, onMounted, provide, ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import DocumentPickerModal from '@/components/documents/DocumentPickerModal.vue';
import DocumentUploadModal from '@/components/documents/DocumentUploadModal.vue';
import InputError from '@/components/InputError.vue';
import type { ProjectRevisionItem } from '@/components/projects/RevisionList.vue';
import TaskTreeList from '@/components/projects/TaskTreeList.vue';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAppToast } from '@/composables/useAppToast';
import { useTranslations } from '@/composables/useTranslations';
import { reorder as reorderTasks, store as storeTask } from '@/routes/projects/tasks';
import {
    complete as completeTask,
    destroy as destroyTask,
    update as updateTask,
} from '@/routes/tasks';
import { store as attachTaskDocument } from '@/routes/tasks/documents';
import type { TaskStatus, TaskTreeNode } from '@/types/task-tree';

export type { TaskStatus, TaskTreeNode };

type Props = {
    projectId: number;
    revisions: ProjectRevisionItem[];
};

const props = defineProps<Props>();

const { t } = useTranslations();
const { showError, showMessage } = useAppToast();

const tasks = ref<TaskTreeNode[]>([]);
const loading = ref(false);
const showForm = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const editingTask = ref<TaskTreeNode | null>(null);
const parentForCreate = ref<number | null>(null);
const showDeleteDialog = ref(false);
const taskToDelete = ref<number | null>(null);
const showDocumentPicker = ref(false);
const showDocumentUpload = ref(false);
const taskForDocuments = ref<number | null>(null);

const emptyFormState = {
    name: '',
    description: '',
    status: 'active' as TaskStatus,
    parent_id: '',
    project_revision_id: '',
};

const form = useForm({ ...emptyFormState });

const flatTasks = computed(() => {
    const result: TaskTreeNode[] = [];

    const walk = (nodes: TaskTreeNode[]): void => {
        for (const node of nodes) {
            result.push(node);
            walk(node.children);
        }
    };

    walk(tasks.value);

    return result;
});

const fetchTasks = async (): Promise<void> => {
    loading.value = true;

    try {
        const response = await fetch(
            `/internal-api/projects/${props.projectId}/tasks`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            },
        );

        if (!response.ok) {
            showError(t('common.error'), t('projects.tasks.load_error'));

            return;
        }

        const payload = (await response.json()) as { data: TaskTreeNode[] };
        tasks.value = payload.data;
    } catch {
        showError(t('common.error'), t('projects.tasks.load_error'));
    } finally {
        loading.value = false;
    }
};

const openCreate = (parentId: number | null = null): void => {
    formMode.value = 'create';
    editingTask.value = null;
    parentForCreate.value = parentId;
    form.defaults({ ...emptyFormState });
    form.reset();
    form.clearErrors();
    form.parent_id = parentId !== null ? String(parentId) : '';
    showForm.value = true;
};

const openEdit = (task: TaskTreeNode): void => {
    formMode.value = 'edit';
    editingTask.value = task;
    form.name = task.name;
    form.description = task.description ?? '';
    form.status = task.status;
    form.parent_id = task.parent_id !== null ? String(task.parent_id) : '';
    form.project_revision_id =
        task.project_revision_id !== null
            ? String(task.project_revision_id)
            : '';
    showForm.value = true;
};

const closeForm = (): void => {
    showForm.value = false;
};

const submit = (): void => {
    const payload = {
        name: form.name,
        description: form.description || null,
        status: form.status,
        parent_id: form.parent_id === '' ? null : Number(form.parent_id),
        project_revision_id:
            form.project_revision_id === ''
                ? null
                : Number(form.project_revision_id),
    };

    const options = {
        preserveScroll: true,
        onSuccess: async () => {
            showMessage(t('common.success'), t('projects.tasks.saved'));
            closeForm();
            await fetchTasks();
        },
        onError: (errors: Record<string, string>) => {
            showError(
                t('common.error'),
                Object.values(errors).flat().join('\n'),
            );
        },
    };

    if (formMode.value === 'create') {
        form.transform(() => payload).post(storeTask(props.projectId).url, options);

        return;
    }

    if (editingTask.value) {
        form.transform(() => payload).put(updateTask(editingTask.value.id).url, options);
    }
};

const complete = (taskId: number): void => {
    router.patch(completeTask(taskId).url, {}, {
        preserveScroll: true,
        onSuccess: () => fetchTasks(),
        onError: (errors: Record<string, string>) => {
            showError(
                t('common.error'),
                Object.values(errors).flat().join('\n'),
            );
        },
    });
};

const persistOrder = (parentId: number | null, taskIds: number[]): void => {
    router.patch(
        reorderTasks(props.projectId).url,
        {
            parent_id: parentId,
            task_ids: taskIds,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                taskIds.forEach((taskId, index) => {
                    const task = flatTasks.value.find((item) => item.id === taskId);

                    if (task) {
                        task.sort_order = index;
                    }
                });
            },
            onError: (errors: Record<string, string>) => {
                showError(
                    t('common.error'),
                    Object.values(errors).flat().join('\n'),
                );
                fetchTasks();
            },
        },
    );
};

const requestDelete = (taskId: number): void => {
    taskToDelete.value = taskId;
    showDeleteDialog.value = true;
};

const confirmDelete = (): void => {
    if (taskToDelete.value === null) {
        return;
    }

    const taskId = taskToDelete.value;
    taskToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(destroyTask(taskId).url, {
        preserveScroll: true,
        onSuccess: () => fetchTasks(),
    });
};

const openDocumentPicker = (taskId: number): void => {
    taskForDocuments.value = taskId;
    showDocumentPicker.value = true;
};

const openDocumentUpload = (taskId: number): void => {
    taskForDocuments.value = taskId;
    showDocumentUpload.value = true;
};

const attachDocument = (documentId: number): void => {
    if (taskForDocuments.value === null) {
        return;
    }

    const taskId = taskForDocuments.value;

    router.post(
        attachTaskDocument({
            task: taskId,
            document: documentId,
        }).url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showMessage(
                    t('common.success'),
                    t('projects.documents.attached_success'),
                );
                fetchTasks();
            },
            onError: (errors: Record<string, string>) => {
                showError(
                    t('common.error'),
                    Object.values(errors).flat().join('\n'),
                );
            },
        },
    );
};

const handleDocumentUploaded = (documentId: number): void => {
    attachDocument(documentId);
};

const statusClass = (status: TaskStatus): string => {
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-900 dark:bg-green-950 dark:text-green-100';
        case 'deferred':
            return 'bg-muted text-muted-foreground';
        default:
            return 'bg-blue-100 text-blue-900 dark:bg-blue-950 dark:text-blue-100';
    }
};

provide('taskTreeActions', {
    complete,
    openCreate,
    openEdit,
    openDocumentPicker,
    openDocumentUpload,
    persistOrder,
    refreshTasks: fetchTasks,
    requestDelete,
    statusClass,
});

onMounted(() => {
    fetchTasks();
});
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-medium">{{ t('projects.tasks.title') }}</h3>
                <p class="text-xs text-muted-foreground">
                    {{ t('projects.tasks.drag_hint') }}
                </p>
            </div>
            <Button type="button" size="sm" variant="outline" @click="openCreate()">
                <Plus class="mr-2 h-4 w-4" />
                {{ t('projects.tasks.add') }}
            </Button>
        </div>

        <div
            v-if="loading"
            class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground"
        >
            {{ t('common.table.loading') }}
        </div>

        <div
            v-else-if="tasks.length === 0"
            class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground"
        >
            {{ t('projects.tasks.empty') }}
        </div>

        <TaskTreeList v-else v-model="tasks" :parent-id="null" />

        <Dialog :open="showForm" @update:open="showForm = $event">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {{
                            formMode === 'create'
                                ? t('projects.tasks.create_title')
                                : t('projects.tasks.edit_title')
                        }}
                    </DialogTitle>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="task-name">{{ t('projects.tasks.fields.name') }}</Label>
                        <Input id="task-name" v-model="form.name" maxlength="120" required />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="task-description">{{
                            t('projects.tasks.fields.description')
                        }}</Label>
                        <textarea
                            id="task-description"
                            v-model="form.description"
                            rows="3"
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        />
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ t('projects.tasks.fields.status') }}</Label>
                        <Select v-model="form.status">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">
                                    {{ t('projects.tasks.status.active') }}
                                </SelectItem>
                                <SelectItem value="completed">
                                    {{ t('projects.tasks.status.completed') }}
                                </SelectItem>
                                <SelectItem value="deferred">
                                    {{ t('projects.tasks.status.deferred') }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.status" />
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ t('projects.tasks.fields.parent') }}</Label>
                        <Select v-model="form.parent_id">
                            <SelectTrigger>
                                <SelectValue :placeholder="t('projects.tasks.fields.no_parent')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">
                                    {{ t('projects.tasks.fields.no_parent') }}
                                </SelectItem>
                                <SelectItem
                                    v-for="task in flatTasks.filter((item) => item.id !== editingTask?.id)"
                                    :key="task.id"
                                    :value="String(task.id)"
                                >
                                    {{ task.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.parent_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ t('projects.tasks.fields.stage') }}</Label>
                        <Select v-model="form.project_revision_id">
                            <SelectTrigger>
                                <SelectValue :placeholder="t('projects.tasks.fields.no_stage')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">
                                    {{ t('projects.tasks.fields.no_stage') }}
                                </SelectItem>
                                <SelectItem
                                    v-for="revision in revisions"
                                    :key="revision.id"
                                    :value="String(revision.id)"
                                >
                                    {{ revision.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.project_revision_id" />
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

        <DocumentPickerModal
            v-model:open="showDocumentPicker"
            @select="attachDocument"
        />

        <DocumentUploadModal
            v-model:open="showDocumentUpload"
            @uploaded="handleDocumentUploaded"
        />

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            :title="t('users.delete_confirm_title')"
            :description="t('projects.tasks.delete_confirm')"
            @confirm="confirmDelete"
            @cancel="taskToDelete = null; showDeleteDialog = false"
        />
    </div>
</template>
