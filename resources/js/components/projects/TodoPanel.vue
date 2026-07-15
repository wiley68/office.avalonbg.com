<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
    destroy as destroyTodo,
    store as storeTodo,
    toggle as toggleTodo,
    update as updateTodo,
} from '@/routes/projects/todos';

export type ProjectTodoItem = {
    id: number;
    body: string;
    status: 'active' | 'completed';
    created_at: string | null;
    completed_at: string | null;
};

type Props = {
    projectId: number;
    todos: ProjectTodoItem[];
};

const props = defineProps<Props>();

const { t } = useTranslations();
const { showError, showMessage } = useAppToast();

const showForm = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const editingTodo = ref<ProjectTodoItem | null>(null);
const showDeleteDialog = ref(false);
const todoToDelete = ref<number | null>(null);

const emptyFormState = {
    body: '',
};

const form = useForm({ ...emptyFormState });

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
};

const isCompleted = (todo: ProjectTodoItem): boolean => todo.status === 'completed';

const openCreate = (): void => {
    formMode.value = 'create';
    editingTodo.value = null;
    form.defaults({ ...emptyFormState });
    form.reset();
    form.clearErrors();
    showForm.value = true;
};

const openEdit = (todo: ProjectTodoItem): void => {
    formMode.value = 'edit';
    editingTodo.value = todo;
    form.body = todo.body;
    form.clearErrors();
    showForm.value = true;
};

const closeForm = (): void => {
    showForm.value = false;
};

const submit = (): void => {
    const payload = {
        body: form.body.trim(),
    };

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showMessage(t('common.success'), t('projects.todos.saved'));
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
        form.transform(() => payload).post(storeTodo(props.projectId).url, options);

        return;
    }

    if (editingTodo.value) {
        form
            .transform(() => payload)
            .put(
                updateTodo({
                    project: props.projectId,
                    todo: editingTodo.value.id,
                }).url,
                options,
            );
    }
};

const toggleStatus = (todo: ProjectTodoItem): void => {
    router.patch(
        toggleTodo({
            project: props.projectId,
            todo: todo.id,
        }).url,
        {},
        { preserveScroll: true },
    );
};

const requestDelete = (todoId: number): void => {
    todoToDelete.value = todoId;
    showDeleteDialog.value = true;
};

const confirmDelete = (): void => {
    if (todoToDelete.value === null) {
        return;
    }

    const todoId = todoToDelete.value;
    todoToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(
        destroyTodo({
            project: props.projectId,
            todo: todoId,
        }).url,
        { preserveScroll: true },
    );
};
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <div class="space-y-4">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h3 class="text-sm font-medium">
                    {{ t('projects.todos.title') }}
                </h3>
                <p class="text-xs text-muted-foreground">
                    {{ t('projects.todos.hint') }}
                </p>
            </div>
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        :aria-label="t('projects.todos.add')"
                        @click="openCreate"
                    >
                        <Plus class="mr-2 h-4 w-4" />
                        {{ t('projects.todos.add') }}
                    </Button>
                </TooltipTrigger>
                <TooltipContent side="top">
                    {{ t('projects.todos.add') }}
                </TooltipContent>
            </Tooltip>
        </div>

        <div
            v-if="todos.length === 0"
            class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground"
        >
            {{ t('projects.todos.empty') }}
        </div>

        <div v-else class="divide-y rounded-md border">
            <div
                v-for="todo in todos"
                :key="todo.id"
                class="flex items-start gap-3 p-4"
            >
                <Checkbox
                    class="mt-0.5"
                    :model-value="isCompleted(todo)"
                    @update:model-value="toggleStatus(todo)"
                />
                <div class="min-w-0 flex-1 space-y-1">
                    <p
                        class="text-sm wrap-break-word whitespace-pre-wrap"
                        :class="{
                            'text-muted-foreground line-through': isCompleted(todo),
                        }"
                    >
                        {{ todo.body }}
                    </p>
                    <p
                        class="text-xs text-muted-foreground"
                        :class="{
                            'line-through opacity-80': isCompleted(todo),
                        }"
                    >
                        {{ t('projects.todos.created_at') }}:
                        {{ formatDateTime(todo.created_at) }}
                    </p>
                </div>
                <div class="flex shrink-0 gap-1">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                class="size-8"
                                :aria-label="t('common.edit')"
                                @click="openEdit(todo)"
                            >
                                <Pencil class="size-4" />
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
                                class="size-8 text-destructive hover:text-destructive"
                                :aria-label="t('common.delete')"
                                @click="requestDelete(todo.id)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="top">
                            {{ t('common.delete') }}
                        </TooltipContent>
                    </Tooltip>
                </div>
            </div>
        </div>

        <Dialog v-model:open="showForm">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {{
                            formMode === 'create'
                                ? t('projects.todos.create_title')
                                : t('projects.todos.edit_title')
                        }}
                    </DialogTitle>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submit">
                    <div class="space-y-2">
                        <Label for="todo-body">{{ t('projects.todos.fields.body') }}</Label>
                        <textarea
                            id="todo-body"
                            v-model="form.body"
                            rows="4"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-[100px] w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            :placeholder="t('projects.todos.fields.body_placeholder')"
                        />
                        <InputError :message="form.errors.body" />
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
            :description="t('projects.todos.delete_confirm')"
            @confirm="confirmDelete"
            @cancel="todoToDelete = null; showDeleteDialog = false"
        />
        </div>
    </TooltipProvider>
</template>
