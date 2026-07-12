<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Check, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import AttachedDocumentsList from '@/components/documents/AttachedDocumentsList.vue';
import DocumentPickerModal from '@/components/documents/DocumentPickerModal.vue';
import InputError from '@/components/InputError.vue';
import type { ProjectRevisionItem } from '@/components/projects/RevisionList.vue';
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
import { store as storeTask } from '@/routes/projects/tasks';
import {
    complete as completeTask,
    destroy as destroyTask,
    reorder as reorderTask,
    update as updateTask,
} from '@/routes/tasks';
import { store as attachTaskDocument } from '@/routes/tasks/documents';

export type TaskStatus = 'active' | 'completed' | 'deferred';

export type TaskTreeNode = {
    id: number;
    project_id: number;
    parent_id: number | null;
    project_revision_id: number | null;
    revision: { id: number; label: string } | null;
    name: string;
    description: string | null;
    status: TaskStatus;
    sort_order: number;
    completed_at: string | null;
    created_at: string | null;
    documents: { id: number; original_name: string }[];
    children: TaskTreeNode[];
};

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

const moveTask = (task: TaskTreeNode, direction: 'up' | 'down'): void => {
    const siblings = flatTasks.value.filter(
        (item) => item.parent_id === task.parent_id,
    );
    const index = siblings.findIndex((item) => item.id === task.id);

    if (index === -1) {
        return;
    }

    const swapIndex = direction === 'up' ? index - 1 : index + 1;

    if (swapIndex < 0 || swapIndex >= siblings.length) {
        return;
    }

    const swapTask = siblings[swapIndex];

    router.patch(
        reorderTask(task.id).url,
        {
            parent_id: task.parent_id,
            sort_order: swapTask.sort_order,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                router.patch(
                    reorderTask(swapTask.id).url,
                    {
                        parent_id: swapTask.parent_id,
                        sort_order: task.sort_order,
                    },
                    {
                        preserveScroll: true,
                        onSuccess: () => fetchTasks(),
                    },
                );
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

const attachDocument = (documentId: number): void => {
    if (taskForDocuments.value === null) {
        return;
    }

    router.post(
        attachTaskDocument({
            task: taskForDocuments.value,
            document: documentId,
        }).url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => fetchTasks(),
        },
    );
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

onMounted(() => {
    fetchTasks();
});
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-medium">{{ t('projects.tasks.title') }}</h3>
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

        <div v-else class="space-y-2">
            <template v-for="task in flatTasks" :key="task.id">
                <div
                    class="rounded-lg border p-4"
                    :style="{ marginLeft: `${(task.parent_id ? 1 : 0) * 24}px` }"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-medium">{{ task.name }}</p>
                                <span
                                    class="inline-block rounded px-2 py-1 text-xs font-medium"
                                    :class="statusClass(task.status)"
                                >
                                    {{ t(`projects.tasks.status.${task.status}`) }}
                                </span>
                                <span
                                    v-if="task.revision"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ task.revision.label }}
                                </span>
                            </div>
                            <p
                                v-if="task.description"
                                class="text-sm text-muted-foreground"
                            >
                                {{ task.description }}
                            </p>
                            <AttachedDocumentsList
                                :documents="task.documents"
                                :detach-url-builder="(documentId) =>
                                    `/tasks/${task.id}/documents/${documentId}`"
                                @detached="fetchTasks"
                            />
                        </div>
                        <div class="flex flex-wrap gap-1">
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                @click="moveTask(task, 'up')"
                            >
                                <ArrowUp class="h-4 w-4" />
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                @click="moveTask(task, 'down')"
                            >
                                <ArrowDown class="h-4 w-4" />
                            </Button>
                            <Button
                                v-if="task.status !== 'completed'"
                                type="button"
                                size="icon"
                                variant="ghost"
                                @click="complete(task.id)"
                            >
                                <Check class="h-4 w-4" />
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                @click="openCreate(task.id)"
                            >
                                <Plus class="h-4 w-4" />
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                @click="openDocumentPicker(task.id)"
                            >
                                {{ t('projects.documents.attach') }}
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                @click="openEdit(task)"
                            >
                                <Pencil class="h-4 w-4" />
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                @click="requestDelete(task.id)"
                            >
                                <Trash2 class="h-4 w-4 text-destructive" />
                            </Button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

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
                        <Label>{{ t('projects.tasks.fields.revision') }}</Label>
                        <Select v-model="form.project_revision_id">
                            <SelectTrigger>
                                <SelectValue :placeholder="t('projects.tasks.fields.no_revision')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">
                                    {{ t('projects.tasks.fields.no_revision') }}
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

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            :title="t('users.delete_confirm_title')"
            :description="t('projects.tasks.delete_confirm')"
            @confirm="confirmDelete"
            @cancel="taskToDelete = null; showDeleteDialog = false"
        />
    </div>
</template>
