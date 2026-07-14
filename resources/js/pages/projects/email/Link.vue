<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ChevronRight, Loader2, Search } from 'lucide-vue-next';
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

type EmailThread = {
    id: string;
    grouping: 'imap_thread' | 'subject';
    message_count: number;
    subject: string;
    latest_sent_at: string | null;
    messages: BrowseMessage[];
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
const threads = ref<EmailThread[]>([]);
const threadsLoading = ref(false);
const selectedFolder = ref<string | null>(props.defaultFolder);
const searchInput = ref('');
const searchQuery = ref('');
const selectedUids = ref<Set<number>>(new Set());
const expandedThreadIds = ref<Set<string>>(new Set());
const attachmentNamesByUid = ref<Record<number, string[]>>({});
const expandedBodyUid = ref<number | null>(null);
const bodies = ref<Record<number, { text: string | null; html: string | null }>>({});
const bodyLoadingUid = ref<number | null>(null);
const submitting = ref(false);

const BODY_EXCERPT_LENGTH = 280;

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('common.dashboard'), href: dashboard() },
    { title: t('projects.title'), href: index() },
    { title: props.project.name, href: show(props.project.id) },
    { title: t('projects.email.link_page_title'), href: '#' },
]);

const allVisibleMessages = computed(() =>
    threads.value.flatMap((thread) => thread.messages),
);

const selectedCount = computed(() => selectedUids.value.size);

const allVisibleSelected = computed(() => {
    if (allVisibleMessages.value.length === 0) {
        return false;
    }

    return allVisibleMessages.value.every((message) => selectedUids.value.has(message.uid));
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

const attachmentLabel = (uid: number): string | null => {
    const names = attachmentNamesByUid.value[uid];

    if (names === undefined || names.length === 0) {
        return null;
    }

    return names.join(', ');
};

const bodyExcerpt = (uid: number): string | null => {
    const body = bodies.value[uid];

    if (body === undefined) {
        return null;
    }

    const raw = body.text ?? body.html?.replace(/<[^>]+>/g, ' ') ?? '';
    const normalized = raw.replace(/\s+/g, ' ').trim();

    if (normalized === '') {
        return null;
    }

    if (normalized.length <= BODY_EXCERPT_LENGTH) {
        return normalized;
    }

    return `${normalized.slice(0, BODY_EXCERPT_LENGTH).trimEnd()}…`;
};

const isThreadExpanded = (threadId: string): boolean => expandedThreadIds.value.has(threadId);

const isThreadFullySelected = (thread: EmailThread): boolean =>
    thread.messages.every((message) => selectedUids.value.has(message.uid));

const isThreadPartiallySelected = (thread: EmailThread): boolean =>
    thread.messages.some((message) => selectedUids.value.has(message.uid))
    && ! isThreadFullySelected(thread);

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

const fetchThreads = async (): Promise<void> => {
    if (selectedFolder.value === null) {
        return;
    }

    threadsLoading.value = true;
    selectedUids.value = new Set();
    expandedThreadIds.value = new Set();
    attachmentNamesByUid.value = {};
    expandedBodyUid.value = null;
    bodies.value = {};

    try {
        const params = new URLSearchParams({
            folder: selectedFolder.value,
            limit: '100',
        });

        const trimmedSearch = searchQuery.value.trim();

        if (trimmedSearch !== '') {
            params.set('search', trimmedSearch);
        }

        const response = await fetch(`/internal-api/imap/threads?${params.toString()}`, {
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

        const payload = (await response.json()) as { data: EmailThread[] };
        threads.value = payload.data;
    } catch {
        showError(t('common.error'), t('projects.email.browse_error'));
    } finally {
        threadsLoading.value = false;
    }
};

const fetchThreadAttachments = async (thread: EmailThread): Promise<void> => {
    if (selectedFolder.value === null) {
        return;
    }

    const uidsToFetch = thread.messages
        .map((message) => message.uid)
        .filter((uid) => attachmentNamesByUid.value[uid] === undefined);

    if (uidsToFetch.length === 0) {
        return;
    }

    try {
        const params = new URLSearchParams({
            folder: selectedFolder.value,
            uids: uidsToFetch.join(','),
        });

        const response = await fetch(`/internal-api/imap/attachments?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const payload = (await response.json()) as { data: Record<string, string[]> };
        const next = { ...attachmentNamesByUid.value };

        for (const uid of uidsToFetch) {
            next[uid] = payload.data[String(uid)] ?? [];
        }

        attachmentNamesByUid.value = next;
    } catch {
        // Attachment names are optional metadata.
    }
};

const selectFolder = (folder: string): void => {
    selectedFolder.value = folder;
};

const toggleThreadExpand = (threadId: string): void => {
    const next = new Set(expandedThreadIds.value);

    if (next.has(threadId)) {
        next.delete(threadId);
    } else {
        next.add(threadId);

        const thread = threads.value.find((item) => item.id === threadId);

        if (thread !== undefined) {
            void fetchThreadAttachments(thread);
        }
    }

    expandedThreadIds.value = next;
};

const toggleBody = async (message: BrowseMessage): Promise<void> => {
    if (expandedBodyUid.value === message.uid) {
        expandedBodyUid.value = null;

        return;
    }

    expandedBodyUid.value = message.uid;

    if (bodies.value[message.uid] !== undefined || selectedFolder.value === null) {
        return;
    }

    bodyLoadingUid.value = message.uid;

    try {
        const params = new URLSearchParams({
            folder: selectedFolder.value,
            uid: String(message.uid),
        });

        const response = await fetch(`/internal-api/imap/body?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            showError(t('common.error'), t('projects.email.body_error'));

            return;
        }

        const payload = (await response.json()) as {
            data: { text: string | null; html: string | null };
        };

        bodies.value = {
            ...bodies.value,
            [message.uid]: payload.data,
        };
    } catch {
        showError(t('common.error'), t('projects.email.body_error'));
    } finally {
        bodyLoadingUid.value = null;
    }
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

const toggleThread = (thread: EmailThread, checked: boolean): void => {
    const next = new Set(selectedUids.value);

    for (const message of thread.messages) {
        if (checked) {
            next.add(message.uid);
        } else {
            next.delete(message.uid);
        }
    }

    selectedUids.value = next;
};

const toggleSelectAllVisible = (checked: boolean): void => {
    const next = new Set(selectedUids.value);

    for (const message of allVisibleMessages.value) {
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

    const selectedMessages = allVisibleMessages.value.filter((message) =>
        selectedUids.value.has(message.uid),
    );

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
    fetchThreads();
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
        fetchThreads();
    }
});

onMounted(async () => {
    await fetchFolders();

    if (selectedFolder.value !== null) {
        await fetchThreads();
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('projects.email.link_page_title')" />

        <div class="mx-2 flex h-[calc(100vh-6rem)] flex-col gap-4">
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
                        v-if="threadsLoading"
                        class="flex flex-1 items-center justify-center text-sm text-muted-foreground"
                    >
                        <Loader2 class="mr-2 size-5 animate-spin" />
                        {{ t('common.table.loading') }}
                    </div>

                    <div
                        v-else-if="threads.length === 0"
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
                            <div
                                v-for="thread in threads"
                                :key="thread.id"
                            >
                                <div class="flex items-start gap-2 px-4 py-3 hover:bg-muted/20">
                                    <Checkbox
                                        class="mt-1"
                                        :model-value="isThreadFullySelected(thread) ? true : isThreadPartiallySelected(thread) ? 'indeterminate' : false"
                                        @update:model-value="(value) => toggleThread(thread, value === true)"
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="mt-0.5 size-8 shrink-0"
                                        :aria-expanded="isThreadExpanded(thread.id)"
                                        @click="toggleThreadExpand(thread.id)"
                                    >
                                        <ChevronRight
                                            class="size-4 transition-transform"
                                            :class="{ 'rotate-90': isThreadExpanded(thread.id) }"
                                        />
                                    </Button>
                                    <button
                                        type="button"
                                        class="min-w-0 flex-1 text-left"
                                        @click="toggleThreadExpand(thread.id)"
                                    >
                                        <p class="truncate font-medium">
                                            {{ thread.subject || t('projects.email.no_subject') }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ t('projects.email.thread_messages', { count: String(thread.message_count) }) }}
                                            · {{ formatDateTime(thread.latest_sent_at) }}
                                        </p>
                                        <p
                                            v-if="thread.messages[0]"
                                            class="mt-1 truncate text-xs text-muted-foreground"
                                        >
                                            {{ fromLabel(thread.messages[0]) }}
                                        </p>
                                    </button>
                                </div>

                                <div
                                    v-if="isThreadExpanded(thread.id)"
                                    class="border-t bg-muted/10"
                                >
                                    <div
                                        v-for="message in thread.messages"
                                        :key="message.uid"
                                        class="border-b border-border/50 last:border-b-0"
                                    >
                                        <div class="flex items-start gap-3 py-2 pr-4 pl-14 hover:bg-muted/20">
                                            <Checkbox
                                                class="mt-1"
                                                :model-value="selectedUids.has(message.uid)"
                                                @update:model-value="(value) => toggleMessage(message.uid, value === true)"
                                                @click.stop
                                            />
                                            <div class="min-w-0 flex-1">
                                                <button
                                                    type="button"
                                                    class="block w-full truncate text-left text-sm font-medium hover:opacity-80"
                                                    @click.stop="toggleMessage(message.uid, !selectedUids.has(message.uid))"
                                                >
                                                    {{ message.subject || t('projects.email.no_subject') }}
                                                </button>
                                                <button
                                                    type="button"
                                                    class="block w-full text-left hover:opacity-80"
                                                    @click="toggleBody(message)"
                                                >
                                                    <p class="truncate text-xs text-muted-foreground">
                                                        {{ fromLabel(message) }}
                                                    </p>
                                                    <p class="text-xs text-muted-foreground">
                                                        {{ formatDateTime(message.sent_at) }}
                                                    </p>
                                                    <p
                                                        v-if="attachmentLabel(message.uid)"
                                                        class="truncate text-xs text-secondary-foreground"
                                                    >
                                                        {{ attachmentLabel(message.uid) }}
                                                    </p>
                                                </button>
                                            </div>
                                        </div>

                                        <div
                                            v-if="expandedBodyUid === message.uid"
                                            class="border-t bg-muted/20 px-4 py-3 pl-14 text-sm"
                                        >
                                            <div
                                                v-if="bodyLoadingUid === message.uid"
                                                class="flex items-center gap-2 text-xs text-muted-foreground"
                                            >
                                                <Loader2 class="size-4 animate-spin" />
                                                {{ t('projects.email.loading_body') }}
                                            </div>
                                            <p
                                                v-else-if="bodyExcerpt(message.uid)"
                                                class="line-clamp-4 text-xs text-muted-foreground whitespace-pre-wrap"
                                            >
                                                {{ bodyExcerpt(message.uid) }}
                                            </p>
                                            <p
                                                v-else
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ t('projects.email.no_body') }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
