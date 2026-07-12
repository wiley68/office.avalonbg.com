<script setup lang="ts">
import {
    Check,
    GripVertical,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-vue-next';
import type { SortableEvent } from 'sortablejs';
import { inject } from 'vue';
import draggable from 'vuedraggable';
import AttachedDocumentsList from '@/components/documents/AttachedDocumentsList.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { TaskStatus, TaskTreeNode } from '@/types/task-tree';

type TaskTreeActions = {
    complete: (taskId: number) => void;
    openCreate: (parentId: number | null) => void;
    openEdit: (task: TaskTreeNode) => void;
    openDocumentPicker: (taskId: number) => void;
    persistOrder: (parentId: number | null, taskIds: number[]) => void;
    refreshTasks: () => Promise<void>;
    requestDelete: (taskId: number) => void;
    statusClass: (status: TaskStatus) => string;
};

type Props = {
    parentId: number | null;
};

const props = defineProps<Props>();

const nodes = defineModel<TaskTreeNode[]>({ required: true });

const { t } = useTranslations();

const actions = inject<TaskTreeActions>('taskTreeActions');

if (!actions) {
    throw new Error('taskTreeActions must be provided by TaskTree.');
}

const dragGroup = `tasks-${props.parentId ?? 'root'}`;

const handleDragEnd = (event: SortableEvent): void => {
    if (event.oldIndex === event.newIndex) {
        return;
    }

    actions.persistOrder(
        props.parentId,
        nodes.value.map((task) => task.id),
    );
};
</script>

<template>
    <draggable
        v-model="nodes"
        item-key="id"
        handle=".task-drag-handle"
        :group="{ name: dragGroup, pull: false, put: false }"
        :animation="180"
        class="space-y-2"
        ghost-class="opacity-50"
        @end="handleDragEnd"
    >
        <template #item="{ element: task }">
            <div class="rounded-lg border bg-background">
                <div class="flex items-start gap-2 p-4">
                    <button
                        type="button"
                        class="task-drag-handle mt-0.5 cursor-grab rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground active:cursor-grabbing"
                        :aria-label="t('projects.tasks.drag_handle')"
                    >
                        <GripVertical class="h-4 w-4" />
                    </button>

                    <div class="min-w-0 flex-1 space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium">{{ task.name }}</p>
                            <span
                                class="inline-block rounded px-2 py-1 text-xs font-medium"
                                :class="actions.statusClass(task.status)"
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
                            @detached="actions.refreshTasks"
                        />
                    </div>

                    <div class="flex flex-wrap gap-1">
                        <Button
                            v-if="task.status !== 'completed'"
                            type="button"
                            size="icon"
                            variant="ghost"
                            @click="actions.complete(task.id)"
                        >
                            <Check class="h-4 w-4" />
                        </Button>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            @click="actions.openCreate(task.id)"
                        >
                            <Plus class="h-4 w-4" />
                        </Button>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            @click="actions.openDocumentPicker(task.id)"
                        >
                            {{ t('projects.documents.attach') }}
                        </Button>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            @click="actions.openEdit(task)"
                        >
                            <Pencil class="h-4 w-4" />
                        </Button>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            @click="actions.requestDelete(task.id)"
                        >
                            <Trash2 class="h-4 w-4 text-destructive" />
                        </Button>
                    </div>
                </div>

                <div
                    v-if="task.children.length > 0"
                    class="border-t px-4 pt-2 pb-4 pl-8"
                >
                    <TaskTreeList
                        v-model="task.children"
                        :parent-id="task.id"
                    />
                </div>
            </div>
        </template>
    </draggable>
</template>
