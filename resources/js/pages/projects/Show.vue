<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ListTodo, Paperclip, Pencil, Upload } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import AttachedDocumentsList from '@/components/documents/AttachedDocumentsList.vue';
import DocumentPickerModal from '@/components/documents/DocumentPickerModal.vue';
import DocumentUploadModal from '@/components/documents/DocumentUploadModal.vue';
import EmailPanel from '@/components/projects/EmailPanel.vue';
import GitPanel from '@/components/projects/GitPanel.vue';
import type { ProjectGitRepositoryConfig } from '@/components/projects/GitPanel.vue';
import ProjectFormModal from '@/components/projects/ProjectFormModal.vue';
import RevisionList from '@/components/projects/RevisionList.vue';
import TaskTree from '@/components/projects/TaskTree.vue';
import TodoPanel from '@/components/projects/TodoPanel.vue';
import type { ProjectTodoItem } from '@/components/projects/TodoPanel.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTranslations } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import { PROJECT_TAB_THEMES, type ProjectSectionTab } from '@/lib/projectTabTheme';
import type { ProjectListItem, ProjectStatus } from '@/pages/projects/columns';
import { dashboard } from '@/routes';
import { index, show } from '@/routes/projects';
import { store as attachProjectDocument } from '@/routes/projects/documents';
import type { BreadcrumbItem } from '@/types';
import type { TaskStatus, TaskTreeNode } from '@/types/task-tree';

type ProjectDocument = {
    id: number;
    original_name: string;
    description: string | null;
    mime_type: string;
};

type ProjectRevision = {
    id: number;
    label: string;
    description: string | null;
    sort_order: number;
};

type Props = {
    project: {
        id: number;
        name: string;
        description: string | null;
        status: ProjectStatus;
        expected_completion_at: string | null;
        completed_at: string | null;
        created_at: string | null;
        revisions: ProjectRevision[];
        documents: ProjectDocument[];
        git_repository: ProjectGitRepositoryConfig | null;
        tasks_count: number;
        email_links_count: number;
        todos: ProjectTodoItem[];
    };
    hasImapConfigured: boolean;
};

const props = defineProps<Props>();

const { t } = useTranslations();

const projectTabs = ['overview', 'stages', 'tasks', 'todos', 'documents', 'git', 'email'] as const;
type ProjectTab = (typeof projectTabs)[number];

const overviewSectionTabs: ProjectSectionTab[] = [
    'stages',
    'tasks',
    'todos',
    'documents',
    'git',
    'email',
];

function resolveTabFromUrl(): ProjectTab {
    const tab = new URLSearchParams(window.location.search).get('tab');

    if (tab === 'revisions') {
        return 'stages';
    }

    if (tab && projectTabs.includes(tab as ProjectTab)) {
        return tab as ProjectTab;
    }

    return 'overview';
}

function resolveStageFromUrl(): number | null {
    const stage = new URLSearchParams(window.location.search).get('stage');

    if (!stage) {
        return null;
    }

    const id = Number(stage);

    return Number.isInteger(id) && id > 0 ? id : null;
}

function updateProjectUrl(tab: ProjectTab, stageId: number | null): void {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);

    if (stageId !== null && tab === 'tasks') {
        url.searchParams.set('stage', String(stageId));
    } else {
        url.searchParams.delete('stage');
    }

    window.history.replaceState({}, '', url.toString());
}

const activeTab = ref<ProjectTab>('overview');
const stageFilterId = ref<number | null>(null);

onMounted(() => {
    activeTab.value = resolveTabFromUrl();
    stageFilterId.value = resolveStageFromUrl();
    fetchOverviewTasks();
});

watch(activeTab, (tab) => {
    updateProjectUrl(tab, tab === 'tasks' ? stageFilterId.value : null);
});

const openTasksForStage = (revisionId: number): void => {
    stageFilterId.value = revisionId;
    activeTab.value = 'tasks';
    updateProjectUrl('tasks', revisionId);
};

const clearStageFilter = (): void => {
    stageFilterId.value = null;
    updateProjectUrl('tasks', null);
};

const openProjectTab = (tab: ProjectSectionTab): void => {
    if (tab !== 'tasks') {
        stageFilterId.value = null;
    }

    activeTab.value = tab;
};

const overviewStages = computed(() => props.project.revisions.slice(0, 5));

const overviewTaskTree = ref<TaskTreeNode[]>([]);

const flattenTaskTree = (nodes: TaskTreeNode[]): TaskTreeNode[] => {
    const result: TaskTreeNode[] = [];

    const walk = (items: TaskTreeNode[]): void => {
        for (const node of items) {
            result.push(node);
            walk(node.children);
        }
    };

    walk(nodes);

    return result;
};

const overviewTasks = computed(() =>
    flattenTaskTree(overviewTaskTree.value).slice(0, 5),
);

const overviewTodos = computed(() => props.project.todos.slice(0, 5));

const overviewDocuments = computed(() => props.project.documents.slice(0, 5));

const overviewSectionCounts = computed<Record<ProjectSectionTab, number>>(() => ({
    stages: props.project.revisions.length,
    tasks: props.project.tasks_count,
    todos: props.project.todos.length,
    documents: props.project.documents.length,
    git: props.project.git_repository !== null ? 1 : 0,
    email: props.project.email_links_count,
}));

const taskStatusClass = (status: TaskStatus): string => {
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-900 dark:bg-green-950 dark:text-green-100';
        case 'deferred':
            return 'bg-muted text-muted-foreground';
        default:
            return 'bg-blue-100 text-blue-900 dark:bg-blue-950 dark:text-blue-100';
    }
};

const fetchOverviewTasks = async (): Promise<void> => {
    try {
        const response = await fetch(
            `/internal-api/projects/${props.project.id}/tasks`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            },
        );

        if (!response.ok) {
            return;
        }

        const payload = (await response.json()) as { data: TaskTreeNode[] };
        overviewTaskTree.value = payload.data;
    } catch {
        // Overview preview only — ignore load failures.
    }
};

const showEditModal = ref(false);
const showDocumentPicker = ref(false);
const showDocumentUpload = ref(false);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('common.dashboard'), href: dashboard() },
    { title: t('projects.title'), href: index() },
    { title: props.project.name, href: show(props.project.id) },
]);

const projectForEdit = computed<ProjectListItem>(() => ({
    id: props.project.id,
    name: props.project.name,
    description: props.project.description,
    status: props.project.status,
    expected_completion_at: props.project.expected_completion_at,
    completed_at: props.project.completed_at,
    created_at: props.project.created_at ?? '',
    documents_count: props.project.documents.length,
    tasks_count: 0,
    email_links_count: 0,
    active_todos_count: 0,
    has_git_repository: props.project.git_repository !== null,
    tasks_timeline: [],
    latest_revision: props.project.revisions[0]
        ? {
              id: props.project.revisions[0].id,
              label: props.project.revisions[0].label,
          }
        : null,
}));

const statusVariant = computed(() => {
    switch (props.project.status) {
        case 'completed':
            return 'default';
        case 'deferred':
            return 'secondary';
        default:
            return 'outline';
    }
});

const formatDate = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString();
};

const attachDocument = (documentId: number): void => {
    router.post(
        attachProjectDocument({
            project: props.project.id,
            document: documentId,
        }).url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['project'] }),
        },
    );
};

const handleDocumentUploaded = (documentId: number): void => {
    attachDocument(documentId);
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="project.name" />

        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-xl font-semibold">{{ project.name }}</h1>
                        <Badge :variant="statusVariant">
                            {{ t(`projects.status.${project.status}`) }}
                        </Badge>
                    </div>
                    <p
                        v-if="project.description"
                        class="max-w-3xl text-sm text-muted-foreground"
                    >
                        {{ project.description }}
                    </p>
                    <div class="flex flex-wrap gap-4 text-sm text-muted-foreground">
                        <span>
                            {{ t('projects.fields.expected_completion_at') }}:
                            {{ formatDate(project.expected_completion_at) }}
                        </span>
                        <span>
                            {{ t('projects.fields.completed_at') }}:
                            {{ formatDate(project.completed_at) }}
                        </span>
                        <span>
                            {{ t('projects.fields.created_at') }}:
                            {{ formatDate(project.created_at) }}
                        </span>
                    </div>
                </div>
                <Button variant="outline" @click="showEditModal = true">
                    <Pencil class="mr-2 h-4 w-4" />
                    {{ t('common.edit') }}
                </Button>
            </div>

            <Tabs v-model="activeTab" class="w-full">
                <TabsList>
                    <TabsTrigger value="overview">
                        {{ t('projects.tabs.overview') }}
                    </TabsTrigger>
                    <TabsTrigger value="stages">
                        {{ t('projects.tabs.stages') }}
                    </TabsTrigger>
                    <TabsTrigger value="tasks">
                        {{ t('projects.tabs.tasks') }}
                    </TabsTrigger>
                    <TabsTrigger value="todos">
                        {{ t('projects.tabs.todos') }}
                    </TabsTrigger>
                    <TabsTrigger value="documents">
                        {{ t('projects.tabs.documents') }}
                    </TabsTrigger>
                    <TabsTrigger value="git">
                        {{ t('projects.tabs.git') }}
                    </TabsTrigger>
                    <TabsTrigger value="email">
                        {{ t('projects.tabs.email') }}
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="overview" class="mt-4">
                    <TooltipProvider :delay-duration="200">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <Card
                                v-for="tab in overviewSectionTabs"
                                :key="tab"
                                class="gap-0 overflow-hidden py-4"
                            >
                                <CardHeader class="border-b pb-4 [.border-b]:pb-4">
                                    <CardTitle class="text-base">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 font-semibold text-foreground underline-offset-4 hover:underline"
                                            @click="openProjectTab(tab)"
                                        >
                                            <component
                                                :is="PROJECT_TAB_THEMES[tab].icon"
                                                class="size-4 shrink-0"
                                                :class="PROJECT_TAB_THEMES[tab].iconClass"
                                                aria-hidden="true"
                                            />
                                            {{ t(`projects.tabs.${tab}`) }}
                                        </button>
                                    </CardTitle>
                                    <CardAction>
                                        <span
                                            class="inline-flex min-w-6 items-center justify-center rounded-md px-2 py-0.5 text-xs font-semibold tabular-nums"
                                            :class="PROJECT_TAB_THEMES[tab].badgeClass"
                                        >
                                            {{ overviewSectionCounts[tab] }}
                                        </span>
                                    </CardAction>
                                </CardHeader>

                                <CardContent
                                    v-if="tab === 'stages'"
                                    class="pt-4"
                                >
                                    <p
                                        v-if="overviewStages.length === 0"
                                        class="text-sm text-muted-foreground"
                                    >
                                        {{ t('projects.stages.empty') }}
                                    </p>

                                    <div
                                        v-else
                                        class="divide-y divide-border/50"
                                    >
                                        <div
                                            v-for="(revision, index) in overviewStages"
                                            :key="revision.id"
                                            class="flex items-start gap-2 py-1 first:pt-0 last:pb-0"
                                        >
                                            <span
                                                class="w-5 shrink-0 pt-0.5 text-xs tabular-nums text-muted-foreground"
                                            >
                                                {{ index + 1 }}.
                                            </span>
                                            <div class="min-w-0 flex-1 space-y-1">
                                                <p class="text-sm font-medium">
                                                    {{ revision.label }}
                                                </p>
                                                <p
                                                    v-if="revision.description"
                                                    class="truncate text-xs text-muted-foreground"
                                                >
                                                    {{ revision.description }}
                                                </p>
                                            </div>

                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        class="size-8 shrink-0"
                                                        :aria-label="t('projects.stages.view_tasks')"
                                                        @click="openTasksForStage(revision.id)"
                                                    >
                                                        <ListTodo class="size-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent side="top">
                                                    {{ t('projects.stages.view_tasks') }}
                                                </TooltipContent>
                                            </Tooltip>
                                        </div>
                                    </div>
                                </CardContent>

                                <CardContent
                                    v-else-if="tab === 'tasks'"
                                    class="pt-4"
                                >
                                    <p
                                        v-if="overviewTasks.length === 0"
                                        class="text-sm text-muted-foreground"
                                    >
                                        {{ t('projects.tasks.empty') }}
                                    </p>

                                    <div
                                        v-else
                                        class="divide-y divide-border/50"
                                    >
                                        <div
                                            v-for="(task, index) in overviewTasks"
                                            :key="task.id"
                                            class="flex min-w-0 gap-2 py-1 first:pt-0 last:pb-0"
                                        >
                                            <span
                                                class="w-5 shrink-0 pt-0.5 text-xs tabular-nums text-muted-foreground"
                                            >
                                                {{ index + 1 }}.
                                            </span>
                                            <div class="min-w-0 flex-1 space-y-1">
                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                <p
                                                    class="text-sm font-medium"
                                                    :class="{
                                                        'line-through': task.status === 'completed',
                                                    }"
                                                >
                                                    {{ task.name }}
                                                </p>
                                                <span
                                                    class="inline-block rounded px-2 py-0.5 text-xs font-medium"
                                                    :class="taskStatusClass(task.status)"
                                                >
                                                    {{ t(`projects.tasks.status.${task.status}`) }}
                                                </span>
                                                <span
                                                    v-if="task.revision"
                                                    class="text-xs text-muted-foreground"
                                                    :class="{
                                                        'line-through': task.status === 'completed',
                                                    }"
                                                >
                                                    {{ task.revision.label }}
                                                </span>
                                            </div>
                                            <p
                                                v-if="task.description"
                                                class="truncate text-xs text-muted-foreground"
                                            >
                                                {{ task.description }}
                                            </p>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>

                                <CardContent
                                    v-else-if="tab === 'todos'"
                                    class="pt-4"
                                >
                                    <p
                                        v-if="overviewTodos.length === 0"
                                        class="text-sm text-muted-foreground"
                                    >
                                        {{ t('projects.todos.empty') }}
                                    </p>

                                    <div
                                        v-else
                                        class="divide-y divide-border/50"
                                    >
                                        <div
                                            v-for="(todo, index) in overviewTodos"
                                            :key="todo.id"
                                            class="flex min-w-0 gap-2 py-1 first:pt-0 last:pb-0"
                                        >
                                            <span
                                                class="w-5 shrink-0 pt-0.5 text-xs tabular-nums text-muted-foreground"
                                            >
                                                {{ index + 1 }}.
                                            </span>
                                            <p
                                                class="min-w-0 flex-1 truncate text-sm"
                                                :class="{
                                                    'text-muted-foreground line-through':
                                                        todo.status === 'completed',
                                                }"
                                            >
                                                {{ todo.body }}
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>

                                <CardContent
                                    v-else-if="tab === 'documents'"
                                    class="pt-4"
                                >
                                    <p
                                        v-if="overviewDocuments.length === 0"
                                        class="text-sm text-muted-foreground"
                                    >
                                        {{ t('projects.documents.none_attached') }}
                                    </p>

                                    <div
                                        v-else
                                        class="divide-y divide-border/50"
                                    >
                                        <div
                                            v-for="(document, index) in overviewDocuments"
                                            :key="document.id"
                                            class="flex min-w-0 gap-2 py-1 first:pt-0 last:pb-0"
                                        >
                                            <span
                                                class="w-5 shrink-0 pt-0.5 text-xs tabular-nums text-muted-foreground"
                                            >
                                                {{ index + 1 }}.
                                            </span>
                                            <div class="min-w-0 flex-1 space-y-1">
                                                <p class="truncate text-sm font-medium">
                                                    {{ document.original_name }}
                                                </p>
                                                <p
                                                    v-if="document.description"
                                                    class="truncate text-xs text-muted-foreground"
                                                >
                                                    {{ document.description }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </TooltipProvider>
                </TabsContent>

                <TabsContent value="stages" class="mt-4">
                    <div class="rounded-xl border p-4 shadow-sm">
                        <RevisionList
                            :project-id="project.id"
                            :revisions="project.revisions"
                            @view-tasks="openTasksForStage"
                        />
                    </div>
                </TabsContent>

                <TabsContent value="tasks" class="mt-4">
                    <div class="rounded-xl border p-4 shadow-sm">
                        <TaskTree
                            :project-id="project.id"
                            :revisions="project.revisions"
                            :stage-filter-id="stageFilterId"
                            @clear-stage-filter="clearStageFilter"
                        />
                    </div>
                </TabsContent>

                <TabsContent value="todos" class="mt-4">
                    <div class="rounded-xl border p-4 shadow-sm">
                        <TodoPanel
                            :project-id="project.id"
                            :todos="project.todos"
                        />
                    </div>
                </TabsContent>

                <TabsContent value="documents" class="mt-4">
                    <div class="space-y-4 rounded-xl border p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-medium">
                                {{ t('projects.documents.title') }}
                            </h3>
                            <div class="flex gap-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    @click="showDocumentUpload = true"
                                >
                                    <Upload class="mr-2 h-4 w-4" />
                                    {{ t('projects.documents.upload') }}
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    @click="showDocumentPicker = true"
                                >
                                    <Paperclip class="mr-2 h-4 w-4" />
                                    {{ t('projects.documents.attach') }}
                                </Button>
                            </div>
                        </div>

                        <AttachedDocumentsList
                            :documents="project.documents"
                            confirm-before-detach
                            :detach-confirm-description="
                                t('projects.documents.detach_confirm_project')
                            "
                            :detach-url-builder="(documentId) =>
                                `/projects/${project.id}/documents/${documentId}`"
                            @detached="router.reload({ only: ['project'] })"
                        />
                    </div>
                </TabsContent>

                <TabsContent value="git" class="mt-4">
                    <div class="rounded-xl border p-4 shadow-sm">
                        <GitPanel
                            :project-id="project.id"
                            :git-repository="project.git_repository"
                        />
                    </div>
                </TabsContent>

                <TabsContent value="email" class="mt-4">
                    <div class="rounded-xl border p-4 shadow-sm">
                        <EmailPanel
                            :project-id="project.id"
                            :has-imap-configured="hasImapConfigured"
                        />
                    </div>
                </TabsContent>
            </Tabs>
        </div>

        <ProjectFormModal
            v-model:open="showEditModal"
            mode="edit"
            :project="projectForEdit"
        />

        <DocumentPickerModal
            v-model:open="showDocumentPicker"
            @select="attachDocument"
        />

        <DocumentUploadModal
            v-model:open="showDocumentUpload"
            @uploaded="handleDocumentUploaded"
        />
    </AppLayout>
</template>
