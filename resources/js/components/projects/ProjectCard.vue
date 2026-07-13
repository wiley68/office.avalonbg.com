<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ListTodo, Paperclip, Pencil, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
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

const hasDocuments = computed(() => props.project.documents_count > 0);

const hasTasks = computed(() => props.project.tasks_count > 0);

const documentsTabUrl = computed(() =>
    show(props.project.id, { query: { tab: 'documents' } }).url,
);

const tasksTabUrl = computed(() =>
    show(props.project.id, { query: { tab: 'tasks' } }).url,
);

const revisionsTabUrl = computed(() =>
    show(props.project.id, { query: { tab: 'revisions' } }).url,
);
</script>

<template>
    <Card class="gap-4 py-4">
        <CardHeader class="gap-2 border-b pb-4 [.border-b]:pb-4">
            <CardTitle class="text-base leading-snug">
                <Link
                    :href="show(project.id)"
                    class="font-semibold text-foreground underline-offset-4 hover:underline"
                >
                    {{ project.name }}
                </Link>
            </CardTitle>
            <CardAction class="flex items-center gap-1">
                <span
                    :class="[
                        'inline-flex rounded px-2 py-0.5 text-xs font-medium',
                        statusClass,
                    ]"
                >
                    {{ t(`projects.status.${project.status}`) }}
                </span>
                <TooltipProvider :delay-duration="200">
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
                                v-if="hasTasks"
                                variant="ghost"
                                size="icon"
                                class="size-8 shrink-0 text-primary hover:text-primary"
                                :aria-label="t('projects.tasks.open_tab')"
                                as-child
                            >
                                <Link :href="tasksTabUrl">
                                    <ListTodo class="size-4" />
                                </Link>
                            </Button>
                            <span v-else class="inline-flex">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 shrink-0 text-muted-foreground"
                                    disabled
                                    :aria-label="t('projects.tasks.none')"
                                >
                                    <ListTodo class="size-4" />
                                </Button>
                            </span>
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
                                        : 'text-muted-foreground hover:text-muted-foreground'
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
                </TooltipProvider>
            </CardAction>
            <CardDescription class="line-clamp-3 text-sm">
                {{ description }}
            </CardDescription>
        </CardHeader>

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
                        {{ t('projects.fields.latest_revision') }}
                    </dt>
                    <dd class="font-medium">
                        <Link
                            v-if="project.latest_revision"
                            :href="revisionsTabUrl"
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
        </CardContent>
    </Card>
</template>
