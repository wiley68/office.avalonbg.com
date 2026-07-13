<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Loader2, Plus } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import ProjectCard from '@/components/projects/ProjectCard.vue';
import ProjectFormModal from '@/components/projects/ProjectFormModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useApiLoadMore } from '@/composables/useApiLoadMore';
import { useAppToast } from '@/composables/useAppToast';
import { projectsApiIndex } from '@/composables/useProjectsApiRoute';
import { useTranslations } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { destroy, index } from '@/routes/projects';
import type { BreadcrumbItem } from '@/types';
import type { ProjectListItem } from './columns';

const { t } = useTranslations();
const { showError } = useAppToast();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('common.dashboard'), href: dashboard() },
    { title: t('projects.title'), href: index() },
]);

const showFormModal = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const editingProject = ref<ProjectListItem | null>(null);
const showDeleteDialog = ref(false);
const projectToDelete = ref<number | null>(null);

const {
    rows,
    loading,
    loadingMore,
    search,
    total,
    hasMore,
    fetch,
    loadMore,
} = useApiLoadMore<ProjectListItem>({
    endpoint: projectsApiIndex().url,
    perPage: 12,
    sortBy: 'id',
    sortDesc: true,
    onError: (message) => {
        showError(t('common.error'), message);
    },
    autoload: false,
});

const openCreate = (): void => {
    formMode.value = 'create';
    editingProject.value = null;
    showFormModal.value = true;
};

const openEdit = (project: ProjectListItem): void => {
    formMode.value = 'edit';
    editingProject.value = project;
    showFormModal.value = true;
};

const requestDelete = (projectId: number): void => {
    projectToDelete.value = projectId;
    showDeleteDialog.value = true;
};

const cancelDelete = (): void => {
    projectToDelete.value = null;
    showDeleteDialog.value = false;
};

const confirmDelete = (): void => {
    if (projectToDelete.value === null) {
        return;
    }

    const projectId = projectToDelete.value;
    projectToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(destroy(projectId).url, {
        preserveScroll: true,
        onSuccess: async () => {
            await fetch();
        },
    });
};

const handleSaved = async (): Promise<void> => {
    await fetch();
};

onMounted(async () => {
    await fetch();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('projects.title')" />

        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h1 class="text-xl font-semibold">
                    {{ t('projects.title') }} ({{ total }})
                </h1>
                <Button class="shrink-0 self-start sm:self-auto" @click="openCreate">
                    <Plus class="mr-2 h-4 w-4" />
                    {{ t('projects.add') }}
                </Button>
            </div>

            <div class="flex flex-col gap-4 rounded-xl border p-4 shadow-sm">
                <Input
                    v-model="search"
                    type="search"
                    :placeholder="t('projects.search_placeholder')"
                    class="max-w-md"
                />

                <div
                    v-if="loading"
                    class="flex items-center justify-center gap-2 py-16 text-muted-foreground"
                >
                    <Loader2 class="size-5 animate-spin" />
                    {{ t('common.table.loading') }}
                </div>

                <p
                    v-else-if="rows.length === 0"
                    class="py-16 text-center text-muted-foreground"
                >
                    {{ t('projects.empty') }}
                </p>

                <template v-else>
                    <div
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"
                    >
                        <ProjectCard
                            v-for="project in rows"
                            :key="project.id"
                            :project="project"
                            @edit="openEdit"
                            @delete="requestDelete"
                        />
                    </div>

                    <div
                        v-if="hasMore"
                        class="flex justify-center pt-2"
                    >
                        <Button
                            variant="outline"
                            class="min-w-40"
                            :disabled="loadingMore"
                            @click="loadMore"
                        >
                            <Loader2
                                v-if="loadingMore"
                                class="mr-2 size-4 animate-spin"
                            />
                            {{ t('projects.show_more') }}
                        </Button>
                    </div>
                </template>
            </div>
        </div>

        <ProjectFormModal
            v-model:open="showFormModal"
            :mode="formMode"
            :project="editingProject"
            @saved="handleSaved"
        />

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            :title="t('users.delete_confirm_title')"
            :description="t('projects.delete_confirm')"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </AppLayout>
</template>
