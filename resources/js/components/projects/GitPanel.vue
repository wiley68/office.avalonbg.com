<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ExternalLink, GitBranch, RefreshCw, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAppToast } from '@/composables/useAppToast';
import { useTranslations } from '@/composables/useTranslations';
import { destroy as destroyGitRepository, upsert as upsertGitRepository } from '@/routes/projects/git';

export type ProjectGitRepositoryConfig = {
    owner: string;
    repo: string;
    default_branch: string;
    repository_url: string;
    has_access_token: boolean;
};

type GitCommit = {
    sha: string;
    short_sha: string;
    message: string;
    author_name: string | null;
    committed_at: string | null;
    html_url: string | null;
};

type GitOverview = {
    repository: ProjectGitRepositoryConfig;
    stats: {
        default_branch: string;
        open_issues_count: number;
        stargazers_count: number;
        forks_count: number;
        pushed_at: string | null;
        last_commit_at: string | null;
        last_commit_message: string | null;
    };
    commits: {
        data: GitCommit[];
        meta: {
            page: number;
            per_page: number;
            has_more: boolean;
        };
    };
};

type Props = {
    projectId: number;
    gitRepository: ProjectGitRepositoryConfig | null;
};

const props = defineProps<Props>();

const { t } = useTranslations();
const { showError, showMessage } = useAppToast();

const overview = ref<GitOverview | null>(null);
const loading = ref(false);
const loadingMore = ref(false);
const loadError = ref<string | null>(null);

const emptyFormState = {
    repository: '',
    default_branch: 'main',
    access_token: '',
};

const form = useForm({ ...emptyFormState });

const isConfigured = computed(() => props.gitRepository !== null);

const repositoryInput = computed({
    get: () =>
        form.repository
        || (props.gitRepository
            ? `${props.gitRepository.owner}/${props.gitRepository.repo}`
            : ''),
    set: (value: string) => {
        form.repository = value;
    },
});

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
};

const firstLine = (message: string | null): string => {
    if (!message) {
        return '—';
    }

    return message.split('\n')[0] ?? message;
};

const fetchOverview = async (page = 1, append = false): Promise<void> => {
    if (!isConfigured.value) {
        return;
    }

    if (append) {
        loadingMore.value = true;
    } else {
        loading.value = true;
        loadError.value = null;
    }

    try {
        const response = await fetch(
            `/internal-api/projects/${props.projectId}/git?page=${page}`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            },
        );

        const payload = await response.json() as {
            data: GitOverview | null;
            message?: string;
            errors?: Record<string, string[]>;
        };

        if (!response.ok) {
            const message = payload.errors
                ? Object.values(payload.errors).flat().join('\n')
                : payload.message ?? t('projects.git.load_error');
            loadError.value = message;
            showError(t('common.error'), message);

            return;
        }

        if (!payload.data) {
            overview.value = null;

            return;
        }

        if (append && overview.value) {
            overview.value = {
                ...payload.data,
                commits: {
                    data: [
                        ...overview.value.commits.data,
                        ...payload.data.commits.data,
                    ],
                    meta: payload.data.commits.meta,
                },
            };
        } else {
            overview.value = payload.data;
        }
    } catch {
        loadError.value = t('projects.git.load_error');
        showError(t('common.error'), t('projects.git.load_error'));
    } finally {
        loading.value = false;
        loadingMore.value = false;
    }
};

const resetForm = (): void => {
    form.defaults({
        repository: props.gitRepository
            ? `${props.gitRepository.owner}/${props.gitRepository.repo}`
            : '',
        default_branch: props.gitRepository?.default_branch ?? 'main',
        access_token: '',
    });
    form.reset();
    form.clearErrors();
};

const saveRepository = (): void => {
    const payload = {
        repository: form.repository,
        default_branch: form.default_branch || 'main',
        access_token: form.access_token === '' ? null : form.access_token,
    };

    form.transform(() => payload).put(upsertGitRepository(props.projectId).url, {
        preserveScroll: true,
        onSuccess: async () => {
            showMessage(t('common.success'), t('projects.git.saved'));
            resetForm();
            router.reload({ only: ['project'] });
        },
        onError: (errors: Record<string, string>) => {
            showError(
                t('common.error'),
                Object.values(errors).flat().join('\n'),
            );
        },
    });
};

const removeRepository = (): void => {
    router.delete(destroyGitRepository(props.projectId).url, {
        preserveScroll: true,
        onSuccess: () => {
            overview.value = null;
            form.defaults({ ...emptyFormState });
            form.reset();
            showMessage(t('common.success'), t('projects.git.removed'));
            router.reload({ only: ['project'] });
        },
    });
};

watch(
    () => props.gitRepository,
    () => {
        resetForm();

        if (props.gitRepository) {
            fetchOverview();
        } else {
            overview.value = null;
        }
    },
);

onMounted(() => {
    resetForm();

    if (props.gitRepository) {
        fetchOverview();
    }
});
</script>

<template>
    <div class="space-y-6">
        <form class="space-y-4 rounded-lg border p-4" @submit.prevent="saveRepository">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="text-sm font-medium">{{ t('projects.git.settings_title') }}</h3>
                    <p class="text-xs text-muted-foreground">
                        {{ t('projects.git.settings_hint') }}
                    </p>
                </div>
                <Button
                    v-if="isConfigured"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="removeRepository"
                >
                    <Trash2 class="mr-2 h-4 w-4" />
                    {{ t('projects.git.remove') }}
                </Button>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2 md:col-span-2">
                    <Label for="git-repository">{{ t('projects.git.fields.repository') }}</Label>
                    <Input
                        id="git-repository"
                        v-model="repositoryInput"
                        placeholder="owner/repository"
                        required
                    />
                    <InputError :message="form.errors.repository" />
                </div>

                <div class="grid gap-2">
                    <Label for="git-branch">{{ t('projects.git.fields.default_branch') }}</Label>
                    <Input
                        id="git-branch"
                        v-model="form.default_branch"
                        placeholder="main"
                    />
                    <InputError :message="form.errors.default_branch" />
                </div>

                <div class="grid gap-2">
                    <Label for="git-token">{{ t('projects.git.fields.access_token') }}</Label>
                    <Input
                        id="git-token"
                        v-model="form.access_token"
                        type="password"
                        autocomplete="off"
                        :placeholder="gitRepository?.has_access_token
                            ? t('projects.git.fields.token_configured')
                            : t('projects.git.fields.token_optional')"
                    />
                    <InputError :message="form.errors.access_token" />
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button type="submit" :disabled="form.processing">
                    {{ isConfigured ? t('common.save') : t('projects.git.connect') }}
                </Button>
                <Button
                    v-if="isConfigured"
                    type="button"
                    variant="outline"
                    :disabled="loading"
                    @click="fetchOverview()"
                >
                    <RefreshCw class="mr-2 h-4 w-4" :class="{ 'animate-spin': loading }" />
                    {{ t('projects.git.refresh') }}
                </Button>
                <Button
                    v-if="gitRepository"
                    variant="outline"
                    as-child
                >
                    <a
                        :href="gitRepository.repository_url"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <ExternalLink class="mr-2 h-4 w-4" />
                        {{ t('projects.git.open_on_github') }}
                    </a>
                </Button>
            </div>
        </form>

        <div
            v-if="!isConfigured"
            class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground"
        >
            {{ t('projects.git.not_configured') }}
        </div>

        <div
            v-else-if="loading && !overview"
            class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground"
        >
            {{ t('common.table.loading') }}
        </div>

        <div
            v-else-if="loadError && !overview"
            class="rounded-md border border-dashed p-6 text-center text-sm text-destructive"
        >
            {{ loadError }}
        </div>

        <template v-else-if="overview">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">
                        {{ t('projects.git.stats.branch') }}
                    </p>
                    <p class="mt-1 flex items-center gap-2 font-medium">
                        <GitBranch class="h-4 w-4" />
                        {{ overview.stats.default_branch }}
                    </p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">
                        {{ t('projects.git.stats.stars') }}
                    </p>
                    <p class="mt-1 font-medium">{{ overview.stats.stargazers_count }}</p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">
                        {{ t('projects.git.stats.forks') }}
                    </p>
                    <p class="mt-1 font-medium">{{ overview.stats.forks_count }}</p>
                </div>
                <div class="rounded-lg border p-4">
                    <p class="text-xs text-muted-foreground">
                        {{ t('projects.git.stats.last_push') }}
                    </p>
                    <p class="mt-1 font-medium">
                        {{ formatDateTime(overview.stats.pushed_at) }}
                    </p>
                </div>
            </div>

            <div class="rounded-lg border p-4">
                <h3 class="text-sm font-medium">{{ t('projects.git.commits_title') }}</h3>
                <p
                    v-if="overview.stats.last_commit_message"
                    class="mt-1 text-xs text-muted-foreground"
                >
                    {{ t('projects.git.latest_commit') }}:
                    {{ firstLine(overview.stats.last_commit_message) }}
                </p>

                <div
                    v-if="overview.commits.data.length === 0"
                    class="mt-4 text-sm text-muted-foreground"
                >
                    {{ t('projects.git.commits_empty') }}
                </div>

                <div v-else class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead>
                            <tr class="border-b text-left text-muted-foreground">
                                <th class="pb-2 pr-4 font-medium">
                                    {{ t('projects.git.columns.commit') }}
                                </th>
                                <th class="pb-2 pr-4 font-medium">
                                    {{ t('projects.git.columns.message') }}
                                </th>
                                <th class="pb-2 pr-4 font-medium">
                                    {{ t('projects.git.columns.author') }}
                                </th>
                                <th class="pb-2 font-medium">
                                    {{ t('projects.git.columns.date') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="commit in overview.commits.data"
                                :key="commit.sha"
                                class="border-b last:border-b-0"
                            >
                                <td class="py-3 pr-4 font-mono text-xs">
                                    <a
                                        v-if="commit.html_url"
                                        :href="commit.html_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-primary underline-offset-4 hover:underline"
                                    >
                                        {{ commit.short_sha }}
                                    </a>
                                    <span v-else>{{ commit.short_sha }}</span>
                                </td>
                                <td class="py-3 pr-4">
                                    {{ firstLine(commit.message) }}
                                </td>
                                <td class="py-3 pr-4 text-muted-foreground">
                                    {{ commit.author_name ?? '—' }}
                                </td>
                                <td class="py-3 text-muted-foreground">
                                    {{ formatDateTime(commit.committed_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="overview.commits.meta.has_more"
                    class="mt-4 flex justify-center"
                >
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="loadingMore"
                        @click="fetchOverview(overview.commits.meta.page + 1, true)"
                    >
                        {{ loadingMore ? t('common.table.loading') : t('projects.git.load_more') }}
                    </Button>
                </div>
            </div>
        </template>
    </div>
</template>
