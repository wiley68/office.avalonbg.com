<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Paperclip, Pencil, Upload } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import AttachedDocumentsList from '@/components/documents/AttachedDocumentsList.vue';
import DocumentPickerModal from '@/components/documents/DocumentPickerModal.vue';
import DocumentUploadModal from '@/components/documents/DocumentUploadModal.vue';
import EmailPanel from '@/components/projects/EmailPanel.vue';
import GitPanel from '@/components/projects/GitPanel.vue';
import type { ProjectGitRepositoryConfig } from '@/components/projects/GitPanel.vue';
import ProjectFormModal from '@/components/projects/ProjectFormModal.vue';
import RevisionList from '@/components/projects/RevisionList.vue';
import TaskTree from '@/components/projects/TaskTree.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTranslations } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ProjectListItem, ProjectStatus } from '@/pages/projects/columns';
import { dashboard } from '@/routes';
import { index, show } from '@/routes/projects';
import { store as attachProjectDocument } from '@/routes/projects/documents';
import type { BreadcrumbItem } from '@/types';

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
    };
    hasImapConfigured: boolean;
};

const props = defineProps<Props>();

const { t } = useTranslations();

const projectTabs = ['overview', 'revisions', 'tasks', 'documents', 'git', 'email'] as const;
type ProjectTab = (typeof projectTabs)[number];

function resolveTabFromUrl(): ProjectTab {
    const tab = new URLSearchParams(window.location.search).get('tab');

    if (tab && projectTabs.includes(tab as ProjectTab)) {
        return tab as ProjectTab;
    }

    return 'overview';
}

const activeTab = ref<ProjectTab>('overview');

onMounted(() => {
    activeTab.value = resolveTabFromUrl();
});

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
                    <TabsTrigger value="revisions">
                        {{ t('projects.tabs.revisions') }}
                    </TabsTrigger>
                    <TabsTrigger value="tasks">
                        {{ t('projects.tabs.tasks') }}
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
                    <div class="rounded-xl border p-4 shadow-sm">
                        <p class="text-sm text-muted-foreground">
                            {{ project.description || t('projects.no_description') }}
                        </p>
                    </div>
                </TabsContent>

                <TabsContent value="revisions" class="mt-4">
                    <div class="rounded-xl border p-4 shadow-sm">
                        <RevisionList
                            :project-id="project.id"
                            :revisions="project.revisions"
                        />
                    </div>
                </TabsContent>

                <TabsContent value="tasks" class="mt-4">
                    <div class="rounded-xl border p-4 shadow-sm">
                        <TaskTree
                            :project-id="project.id"
                            :revisions="project.revisions"
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
