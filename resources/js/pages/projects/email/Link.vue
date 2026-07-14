<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Loader2, Search } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import ImapFolderTree, { type ImapFolderNode } from '@/components/projects/ImapFolderTree.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useAppToast } from '@/composables/useAppToast';
import { useTranslations } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index, show } from '@/routes/projects';
import { link as linkProjectEmail } from '@/routes/projects/email';
import batch from '@/routes/projects/email/batch';
import type { BreadcrumbItem } from '@/types';

type BrowseMessage = {
    uid: number;
    uidvalidity: number;
    subject: string;
    from_name: string | null;
    from_address: string | null;
    sent_at: string | null;
};

type Props = {
    project: {
        id: number;
        name: string;
    };
    defaultFolder: string;
};

const props = defineProps<Props>();

const { t } = useTranslations();
const { showError, showMessage } = useAppToast();

const folders = ref<ImapFolderNode[]>([]);
const foldersLoading = ref(true);
const messages = ref<BrowseMessage[]>([]);
const messagesLoading = ref(false);
const selectedFolder = ref<string | null>(props.defaultFolder);
const searchInput = ref('');
const searchQuery = ref('');
const selectedUids = ref<Set<number>>(new Set());
const submitting = ref(false);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('common.dashboard'), href: dashboard() },
    { title: t('projects.title'), href: index() },
    { title: props.project.name, href: show(props.project.id) },
    { title: t('projects.email.link_page_title'), href: '#' },
]);

const filteredMessages = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (query === '') {
        return messages.value;
    }

    return messages.value.filter(
        (message) =>
            message.subject.toLowerCase().includes(query)
            || (message.from_name ?? '').toLowerCase().includes(query)
            || (message.from_address ?? '').toLowerCase().includes(query),
    );
});

const selectedCount = computed(() => selectedUids.value.size);

const allVisibleSelected = computed(() => {
    if (filteredMessages.value.length === 0) {
        return false;
    }

    return filteredMessages.value.every((message) => selectedUids.value.has(message.uid));
});

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
};

const fromLabel = (message: BrowseMessage): string => {
    if (message.from_name && message.from_address) {
        return `${message.from_name} <${message.from_address}>`;
    }

    return message.from_address ?? message.from_name ?? '—';
};

const fetchFolders = async (): Promise<void> => {
    foldersLoading.value = true;

    try {
        const response = await fetch('/internal-api/imap/folders', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            showError(t('common.error'), t('projects.email.folders_error'));

            return;
        }

        const payload = (await response.json()) as {
            data: ImapFolderNode[];
            meta?: { default_folder?: string };
        };

        folders.value = payload.data;

        if (!selectedFolder.value && payload.meta?.default_folder) {
            selectedFolder.value = payload.meta.default_folder;
        }
    } catch {
        showError(t('common.error'), t('projects.email.folders_error'));
    } finally {
        foldersLoading.value = false;
    }
};

const fetchMessages = async (): Promise<void> => {
    if (selectedFolder.value === null) {
        return;
    }

    messagesLoading.value = true;
    selectedUids.value = new Set();

    try {
        const params = new URLSearchParams({
            folder: selectedFolder.value,
            limit: '100',
        });

        const trimmedSearch = searchQuery.value.trim();

        if (trimmedSearch !== '') {
            params.set('search', trimmedSearch);
        }

        const response = await fetch(`/internal-api/imap/messages?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            showError(t('common.error'), t('projects.email.browse_error'));

            return;
        }

        const payload = (await response.json()) as { data: BrowseMessage[] };
        messages.value = payload.data;
    } catch {
        showError(t('common.error'), t('projects.email.browse_error'));
    } finally {
        messagesLoading.value = false;
    }
};

const selectFolder = (folder: string): void => {
    selectedFolder.value = folder;
};

const toggleMessage = (uid: number, checked: boolean): void => {
    const next = new Set(selectedUids.value);

    if (checked) {
        next.add(uid);
    } else {
        next.delete(uid);
    }

    selectedUids.value = next;
};

const toggleSelectAllVisible = (checked: boolean): void => {
    const next = new Set(selectedUids.value);

    for (const message of filteredMessages.value) {
        if (checked) {
            next.add(message.uid);
        } else {
            next.delete(message.uid);
        }
    }

    selectedUids.value = next;
};

const submitSelection = (): void => {
    if (selectedFolder.value === null || selectedCount.value === 0) {
        return;
    }

    const selectedMessages = messages.value.filter((message) => selectedUids.value.has(message.uid));

    submitting.value = true;

    router.post(
        batch.store(props.project.id).url,
        {
            messages: selectedMessages.map((message) => ({
                folder: selectedFolder.value,
                imap_uid: message.uid,
                uidvalidity: message.uidvalidity,
                subject: message.subject,
                from_name: message.from_name,
                from_address: message.from_address,
                sent_at: message.sent_at,
            })),
        },
        {
            onSuccess: () => {
                showMessage(t('common.success'), t('projects.email.linked_batch'));
            },
            onError: (errors) => {
                showError(t('common.error'), Object.values(errors).flat().join('\n'));
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
};

const backToProject = show(props.project.id, { query: { tab: 'email' } });

watch(selectedFolder, () => {
    fetchMessages();
});

watch(searchInput, () => {
    window.clearTimeout(searchDebounceTimer);
    searchDebounceTimer = window.setTimeout(() => {
        searchQuery.value = searchInput.value;
    }, 400);
});

let searchDebounceTimer = 0;

watch(searchQuery, () => {
    if (selectedFolder.value !== null) {
        fetchMessages();
    }
});

onMounted(async () => {
    await fetchFolders();

    if (selectedFolder.value !== null) {
        await fetchMessages();
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('projects.email.link_page_title')" />

        <div class="flex h-[calc(100vh-8rem)] flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="backToProject">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            {{ t('projects.email.back_to_project') }}
                        </Link>
                    </Button>
                    <div>
                        <h1 class="text-lg font-semibold">
                            {{ t('projects.email.link_page_title') }}
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            {{ project.name }}
                        </p>
                    </div>
                </div>

                <Button
                    type="button"
                    :disabled="selectedCount === 0 || submitting"
                    @click="submitSelection"
                >
                    <Loader2
                        v-if="submitting"
                        class="mr-2 h-4 w-4 animate-spin"
                    />
                    {{ t('projects.email.link_selected', { count: String(selectedCount) }) }}
                </Button>
            </div>

            <div class="grid min-h-0 flex-1 gap-4 lg:grid-cols-[280px_minmax(0,1fr)]">
                <aside class="flex min-h-0 flex-col rounded-xl border bg-muted/20">
                    <div class="border-b px-4 py-3">
                        <h2 class="text-sm font-medium">
                            {{ t('projects.email.folders_title') }}
                        </h2>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto p-2">
                        <ImapFolderTree
                            :folders="folders"
                            :selected-folder="selectedFolder"
                            :loading="foldersLoading"
                            @select="selectFolder"
                        />
                    </div>
                </aside>

                <section class="flex min-h-0 flex-col rounded-xl border">
                    <div class="flex flex-wrap items-center gap-3 border-b px-4 py-3">
                        <div class="relative min-w-[220px] flex-1">
                            <Search class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="searchInput"
                                type="search"
                                class="pl-9"
                                :placeholder="t('projects.email.browse_search')"
                            />
                        </div>
                        <p
                            v-if="selectedFolder"
                            class="text-xs text-muted-foreground"
                        >
                            {{ selectedFolder }}
                        </p>
                    </div>

                    <div
                        v-if="messagesLoading"
                        class="flex flex-1 items-center justify-center text-sm text-muted-foreground"
                    >
                        <Loader2 class="mr-2 size-5 animate-spin" />
                        {{ t('common.table.loading') }}
                    </div>

                    <div
                        v-else-if="filteredMessages.length === 0"
                        class="flex flex-1 items-center justify-center p-6 text-sm text-muted-foreground"
                    >
                        {{ t('projects.email.browse_empty') }}
                    </div>

                    <div
                        v-else
                        class="min-h-0 flex-1 overflow-y-auto"
                    >
                        <div class="sticky top-0 z-10 flex items-center gap-3 border-b bg-background px-4 py-2">
                            <Checkbox
                                :model-value="allVisibleSelected"
                                @update:model-value="(value) => toggleSelectAllVisible(value === true)"
                            />
                            <span class="text-xs text-muted-foreground">
                                {{ t('projects.email.select_all_visible') }}
                            </span>
                        </div>

                        <div class="divide-y">
                            <label
                                v-for="message in filteredMessages"
                                :key="message.uid"
                                class="flex cursor-pointer items-start gap-3 px-4 py-3 hover:bg-muted/30"
                            >
                                <Checkbox
                                    class="mt-1"
                                    :model-value="selectedUids.has(message.uid)"
                                    @update:model-value="(value) => toggleMessage(message.uid, value === true)"
                                    @click.stop
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium">
                                        {{ message.subject || t('projects.email.no_subject') }}
                                    </p>
                                    <p class="truncate text-xs text-muted-foreground">
                                        {{ fromLabel(message) }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ formatDateTime(message.sent_at) }}
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
