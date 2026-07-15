<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { GitBranch, ListChecks, ListTodo, Mail, Paperclip, Pencil, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import ProjectTaskTimeline from '@/components/projects/ProjectTaskTimeline.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTranslations } from '@/composables/useTranslations';
import { show } from '@/routes/projects';
import type { ProjectListItem } from '@/pages/projects/columns';
import { formatProjectDate, projectStatusBadgeClass } from '@/pages/projects/columns';

const props = defineProps<{
    project: ProjectListItem;
}>();

const emit = defineEmits<{
    edit: [project: ProjectListItem];
    delete: [projectId: number];
}>();

const { t } = useTranslations();

const statusClass = computed(() =>
    projectStatusBadgeClass(props.project.status),
);

const description = computed(
    () => props.project.description?.trim() || t('projects.no_description'),
);

const hasDescription = computed(
    () => (props.project.description?.trim() ?? '') !== '',
);

const hasDocuments = computed(() => props.project.documents_count > 0);

const hasTasks = computed(() => props.project.tasks_count > 0);

const hasGitRepository = computed(() => props.project.has_git_repository);

const hasEmailLinks = computed(() => props.project.email_links_count > 0);

const hasActiveTodos = computed(() => props.project.active_todos_count > 0);

const tasksTimeline = computed(
    () => props.project.tasks_timeline ?? [],
);

const documentsTabUrl = computed(() =>
    show(props.project.id, { query: { tab: 'documents' } }).url,
);

const tasksTabUrl = computed(() =>
    show(props.project.id, { query: { tab: 'tasks' } }).url,
);

const gitTabUrl = computed(() =>
    show(props.project.id, { query: { tab: 'git' } }).url,
);

const emailTabUrl = computed(() =>
    show(props.project.id, { query: { tab: 'email' } }).url,
);

const todosTabUrl = computed(() =>
    show(props.project.id, { query: { tab: 'todos' } }).url,
);

const stagesTabUrl = computed(() =>
    show(props.project.id, { query: { tab: 'stages' } }).url,
);
</script>

<template>
    <Card class="gap-4 overflow-hidden py-4">
        <TooltipProvider :delay-duration="200">
            <CardHeader class="min-w-0 gap-2 border-b pb-4 [.border-b]:pb-4">
                <CardTitle class="min-w-0 text-base leading-snug">
                    <Link
                        :href="show(project.id)"
                        class="line-clamp-2 block font-semibold text-foreground underline-offset-4 hover:underline"
                    >
                        {{ project.name }}
                    </Link>
                </CardTitle>

                <div class="h-5 min-w-0 w-full overflow-hidden">
                    <Tooltip v-if="hasDescription">
                        <TooltipTrigger as-child>
                            <CardDescription
                                class="block cursor-default truncate text-sm leading-5"
                            >
                                {{ description }}
                            </CardDescription>
                        </TooltipTrigger>
                        <TooltipContent side="top" class="max-w-sm text-pretty">
                            {{ description }}
                        </TooltipContent>
                    </Tooltip>
                    <CardDescription
                        v-else
                        class="block truncate text-sm leading-5"
                    >
                        {{ description }}
                    </CardDescription>
                </div>

                <div class="flex flex-wrap items-center gap-1">
                    <span
                        :class="[
                            'inline-flex rounded px-2 py-0.5 text-xs font-medium',
                            statusClass,
                        ]"
                    >
                        {{ t(`projects.status.${project.status}`) }}
                    </span>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8 shrink-0"
                                :class="
                                    hasTasks
                                        ? 'text-primary hover:text-primary'
                                        : 'text-muted-foreground/50 hover:text-muted-foreground/65'
                                "
                                :aria-label="
                                    hasTasks
                                        ? t('projects.tasks.open_tab')
                                        : t('projects.tasks.none')
                                "
                                as-child
                            >
                                <Link :href="tasksTabUrl">
                                    <ListTodo class="size-4" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="top">
                            {{
                                hasTasks
                                    ? t('projects.tasks.open_tab')
                                    : t('projects.tasks.none')
                            }}
                        </TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8 shrink-0"
                                :class="
                                    hasDocuments
                                        ? 'text-primary hover:text-primary'
                                        : 'text-muted-foreground/50 hover:text-muted-foreground/65'
                                "
                                :aria-label="
                                    hasDocuments
                                        ? t('projects.documents.open_tab')
                                        : t('projects.documents.none_attached')
                                "
                                as-child
                            >
                                <Link :href="documentsTabUrl">
                                    <Paperclip class="size-4" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="top">
                            {{
                                hasDocuments
                                    ? t('projects.documents.open_tab')
                                    : t('projects.documents.none_attached')
                            }}
                        </TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8 shrink-0"
                                :class="
                                    hasGitRepository
                                        ? 'text-primary hover:text-primary'
                                        : 'text-muted-foreground/50 hover:text-muted-foreground/65'
                                "
                                :aria-label="
                                    hasGitRepository
                                        ? t('projects.git.open_tab')
                                        : t('projects.git.none')
                                "
                                as-child
                            >
                                <Link :href="gitTabUrl">
                                    <GitBranch class="size-4" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="top">
                            {{
                                hasGitRepository
                                    ? t('projects.git.open_tab')
                                    : t('projects.git.none')
                            }}
                        </TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8 shrink-0"
                                :class="
                                    hasEmailLinks
                                        ? 'text-primary hover:text-primary'
                                        : 'text-muted-foreground/50 hover:text-muted-foreground/65'
                                "
                                :aria-label="
                                    hasEmailLinks
                                        ? t('projects.email.open_tab')
                                        : t('projects.email.none_linked')
                                "
                                as-child
                            >
                                <Link :href="emailTabUrl">
                                    <Mail class="size-4" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="top">
                            {{
                                hasEmailLinks
                                    ? t('projects.email.open_tab')
                                    : t('projects.email.none_linked')
                            }}
                        </TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8 shrink-0"
                                :class="
                                    hasActiveTodos
                                        ? 'text-primary hover:text-primary'
                                        : 'text-muted-foreground/50 hover:text-muted-foreground/65'
                                "
                                :aria-label="
                                    hasActiveTodos
                                        ? t('projects.todos.open_tab')
                                        : t('projects.todos.none_active')
                                "
                                as-child
                            >
                                <Link :href="todosTabUrl">
                                    <ListChecks class="size-4" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="top">
                            {{
                                hasActiveTodos
                                    ? t('projects.todos.open_tab')
                                    : t('projects.todos.none_active')
                            }}
                        </TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8 shrink-0"
                                :aria-label="t('common.edit')"
                                @click="emit('edit', project)"
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
                                variant="ghost"
                                size="icon"
                                class="size-8 shrink-0 text-destructive hover:text-destructive"
                                :aria-label="t('common.delete')"
                                @click="emit('delete', project.id)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="top">
                            {{ t('common.delete') }}
                        </TooltipContent>
                    </Tooltip>
                </div>
            </CardHeader>
        </TooltipProvider>

        <CardContent class="px-6 pt-0">
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-muted-foreground">
                        {{ t('projects.fields.created_at') }}
                    </dt>
                    <dd class="font-medium">
                        {{ formatProjectDate(project.created_at) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ t('projects.fields.expected_completion_at') }}
                    </dt>
                    <dd class="font-medium">
                        {{ formatProjectDate(project.expected_completion_at) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ t('projects.fields.current_stage') }}
                    </dt>
                    <dd class="font-medium">
                        <Link
                            v-if="project.latest_revision"
                            :href="stagesTabUrl"
                            class="text-foreground underline-offset-4 hover:underline"
                        >
                            {{ project.latest_revision.label }}
                        </Link>
                        <span v-else>—</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ t('projects.fields.completed_at') }}
                    </dt>
                    <dd class="font-medium">
                        {{ formatProjectDate(project.completed_at) }}
                    </dd>
                </div>
            </dl>

            <div
                v-if="tasksTimeline.length > 0"
                class="mt-4 border-t pt-4"
            >
                <ProjectTaskTimeline
                    :tasks="tasksTimeline"
                    :project-status="project.status"
                />
            </div>
        </CardContent>
    </Card>
</template>
