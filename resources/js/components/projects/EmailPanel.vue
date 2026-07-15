<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ChevronRight, Loader2, MailPlus, RefreshCw, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppToast } from '@/composables/useAppToast';
import { useTranslations } from '@/composables/useTranslations';
import { edit as editEmailSettings } from '@/routes/email';
import { destroy as destroyEmailLink, link as linkProjectEmail } from '@/routes/projects/email';
import { destroy as destroyEmailThread } from '@/routes/projects/email/thread';

type EmailLink = {
    id: number;
    folder: string;
    imap_uid: number;
    uidvalidity: number;
    subject: string | null;
    from_name: string | null;
    from_address: string | null;
    sent_at: string | null;
    status: 'active' | 'missing_on_server';
    is_archived?: boolean;
    attachments?: EmailAttachment[];
    last_verified_at: string | null;
};

type EmailThread = {
    id: string;
    message_count: number;
    subject: string;
    latest_sent_at: string | null;
    messages: EmailLink[];
};

type EmailAttachment = {
    part: string;
    filename: string;
};

type Props = {
    projectId: number;
    hasImapConfigured: boolean;
};

const props = defineProps<Props>();

const { t } = useTranslations();
const { showError } = useAppToast();

const threads = ref<EmailThread[]>([]);
const loading = ref(false);
const loadError = ref<string | null>(null);
const expandedThreadIds = ref<Set<string>>(new Set());
const expandedLinkId = ref<number | null>(null);
const bodyLoadingId = ref<number | null>(null);
const bodies = ref<Record<number, { text: string | null; html: string | null }>>({});
const attachmentsByLinkId = ref<Record<number, EmailAttachment[]>>({});
const linkToDelete = ref<number | null>(null);
const threadLinkIdsToDelete = ref<number[] | null>(null);
const showDeleteDialog = ref(false);

const deleteDialogDescription = computed(() => {
    if (threadLinkIdsToDelete.value !== null) {
        return t('projects.email.unlink_thread_confirm', {
            count: String(threadLinkIdsToDelete.value.length),
        });
    }

    return t('projects.email.unlink_confirm');
});

const totalMessageCount = computed(() =>
    threads.value.reduce((count, thread) => count + thread.message_count, 0),
);

const showImapSetupNotice = computed(
    () => !props.hasImapConfigured && totalMessageCount.value === 0 && !loading.value,
);

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
};

const fromLabel = (link: EmailLink): string => {
    if (link.from_name && link.from_address) {
        return `${link.from_name} <${link.from_address}>`;
    }

    return link.from_address ?? link.from_name ?? '—';
};

const isThreadExpanded = (threadId: string): boolean => expandedThreadIds.value.has(threadId);

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

const attachmentDownloadUrl = (linkId: number, part: string): string =>
    `/internal-api/projects/${props.projectId}/email/${linkId}/attachments/${encodeURIComponent(part)}`;

const fetchLinkAttachments = async (link: EmailLink): Promise<void> => {
    if (link.status === 'missing_on_server' || attachmentsByLinkId.value[link.id] !== undefined) {
        return;
    }

    try {
        const response = await fetch(
            `/internal-api/projects/${props.projectId}/email/${link.id}/attachments`,
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

        const payload = (await response.json()) as { data: EmailAttachment[] };

        attachmentsByLinkId.value = {
            ...attachmentsByLinkId.value,
            [link.id]: payload.data,
        };
    } catch {
        // Attachment names are optional metadata.
    }
};

const fetchThreadAttachments = async (thread: EmailThread): Promise<void> => {
    await Promise.all(thread.messages.map((link) => fetchLinkAttachments(link)));
};

const preloadArchivedAttachments = (loadedThreads: EmailThread[]): void => {
    const next = { ...attachmentsByLinkId.value };

    for (const thread of loadedThreads) {
        for (const link of thread.messages) {
            if (link.attachments !== undefined && link.attachments.length > 0) {
                next[link.id] = link.attachments;
            }
        }
    }

    attachmentsByLinkId.value = next;
};

const fetchThreads = async (): Promise<void> => {
    loading.value = true;
    loadError.value = null;

    try {
        const response = await fetch(
            `/internal-api/projects/${props.projectId}/email`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            },
        );

        if (!response.ok) {
            const payload = (await response.json()) as { message?: string };
            loadError.value = payload.message ?? t('projects.email.load_error');

            return;
        }

        const payload = (await response.json()) as {
            data: { configured: boolean; threads: EmailThread[] };
        };

        threads.value = payload.data.threads;
        attachmentsByLinkId.value = {};
        preloadArchivedAttachments(payload.data.threads);
    } catch {
        loadError.value = t('projects.email.load_error');
        showError(t('common.error'), t('projects.email.load_error'));
    } finally {
        loading.value = false;
    }
};

const toggleBody = async (link: EmailLink): Promise<void> => {
    if (link.status === 'missing_on_server') {
        return;
    }

    if (expandedLinkId.value === link.id) {
        expandedLinkId.value = null;

        return;
    }

    expandedLinkId.value = link.id;

    if (bodies.value[link.id]) {
        return;
    }

    bodyLoadingId.value = link.id;

    try {
        const response = await fetch(
            `/internal-api/projects/${props.projectId}/email/${link.id}/body`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            },
        );

        if (!response.ok) {
            showError(t('common.error'), t('projects.email.body_error'));

            return;
        }

        const payload = (await response.json()) as {
            data: { text: string | null; html: string | null };
        };

        bodies.value[link.id] = payload.data;
    } catch {
        showError(t('common.error'), t('projects.email.body_error'));
    } finally {
        bodyLoadingId.value = null;
    }
};

const requestDelete = (linkId: number): void => {
    threadLinkIdsToDelete.value = null;
    linkToDelete.value = linkId;
    showDeleteDialog.value = true;
};

const requestThreadDelete = (thread: EmailThread): void => {
    linkToDelete.value = null;
    threadLinkIdsToDelete.value = thread.messages.map((link) => link.id);
    showDeleteDialog.value = true;
};

const confirmDelete = (): void => {
    if (threadLinkIdsToDelete.value !== null) {
        const linkIds = threadLinkIdsToDelete.value;
        threadLinkIdsToDelete.value = null;
        showDeleteDialog.value = false;

        router.delete(destroyEmailThread(props.projectId).url, {
            data: { link_ids: linkIds },
            preserveScroll: true,
            onSuccess: () => fetchThreads(),
        });

        return;
    }

    if (linkToDelete.value === null) {
        return;
    }

    const linkId = linkToDelete.value;
    linkToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(destroyEmailLink({ project: props.projectId, emailLink: linkId }).url, {
        preserveScroll: true,
        onSuccess: () => fetchThreads(),
    });
};

onMounted(() => {
    fetchThreads();
});
</script>

<template>
    <div class="space-y-6">
        <div
            v-if="showImapSetupNotice"
            class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground"
        >
            <p>{{ t('projects.email.not_configured') }}</p>
            <Button class="mt-4" variant="outline" as-child>
                <Link :href="editEmailSettings()">
                    {{ t('projects.email.open_settings') }}
                </Link>
            </Button>
        </div>

        <template v-else>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-sm font-medium">{{ t('projects.email.title') }}</h3>
                    <p class="text-xs text-muted-foreground">
                        {{ t('projects.email.hint') }}
                    </p>
                </div>
                <TooltipProvider :delay-duration="200">
                    <div class="flex gap-1">
                        <Tooltip v-if="hasImapConfigured">
                            <TooltipTrigger as-child>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    as-child
                                >
                                    <Link :href="linkProjectEmail(projectId)">
                                        <MailPlus class="mr-2 h-4 w-4" />
                                        {{ t('projects.email.link_message') }}
                                    </Link>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="top">
                                {{ t('projects.email.link_message') }}
                            </TooltipContent>
                        </Tooltip>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    :disabled="loading"
                                    @click="fetchThreads"
                                >
                                    <RefreshCw
                                        class="mr-2 h-4 w-4"
                                        :class="{ 'animate-spin': loading }"
                                    />
                                    {{ t('projects.email.refresh') }}
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="top">
                                {{ t('projects.email.refresh') }}
                            </TooltipContent>
                        </Tooltip>
                    </div>
                </TooltipProvider>
            </div>

            <div
                v-if="loading && totalMessageCount === 0"
                class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                <Loader2 class="mx-auto mb-2 size-5 animate-spin" />
                {{ t('common.table.loading') }}
            </div>

            <div
                v-else-if="loadError && totalMessageCount === 0"
                class="rounded-md border border-dashed p-6 text-center text-sm text-destructive"
            >
                {{ loadError }}
            </div>

            <div
                v-else-if="totalMessageCount === 0"
                class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                {{ t('projects.email.empty') }}
            </div>

            <div
                v-else
                class="divide-y rounded-md border"
            >
                <div
                    v-for="thread in threads"
                    :key="thread.id"
                >
                    <div class="flex items-start gap-2 px-4 py-3 hover:bg-muted/20">
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
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            class="mt-0.5 shrink-0 text-destructive hover:text-destructive"
                            :aria-label="t('projects.email.unlink_thread')"
                            @click.stop="requestThreadDelete(thread)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>

                    <div
                        v-if="isThreadExpanded(thread.id)"
                        class="border-t bg-muted/10"
                    >
                        <div
                            v-for="link in thread.messages"
                            :key="link.id"
                            class="border-b border-border/50 last:border-b-0"
                            :class="{
                                'opacity-70': link.status === 'missing_on_server',
                            }"
                        >
                            <div class="flex items-start gap-3 py-2 pr-4 pl-14">
                                <div class="min-w-0 flex-1">
                                    <button
                                        type="button"
                                        class="w-full text-left"
                                        :class="{
                                            'cursor-pointer hover:opacity-80': link.status === 'active',
                                            'cursor-not-allowed': link.status === 'missing_on_server',
                                        }"
                                        @click="toggleBody(link)"
                                    >
                                        <p
                                            class="truncate text-sm font-medium"
                                            :class="{
                                                'line-through': link.status === 'missing_on_server',
                                            }"
                                        >
                                            {{ link.subject || t('projects.email.no_subject') }}
                                        </p>
                                        <p class="truncate text-xs text-muted-foreground">
                                            {{ fromLabel(link) }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ formatDateTime(link.sent_at) }}
                                            <span v-if="link.status === 'missing_on_server'">
                                                · {{ t('projects.email.missing_on_server') }}
                                            </span>
                                        </p>
                                    </button>
                                    <div
                                        v-if="attachmentsByLinkId[link.id]?.length"
                                        class="mt-1 flex flex-wrap gap-2"
                                    >
                                        <a
                                            v-for="attachment in attachmentsByLinkId[link.id]"
                                            :key="`${link.id}-${attachment.part}`"
                                            :href="attachmentDownloadUrl(link.id, attachment.part)"
                                            class="inline-flex max-w-full items-center rounded-md border border-border bg-background px-2 py-0.5 text-xs text-blue-600 hover:bg-muted hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                                            download
                                            @click.stop
                                        >
                                            <span class="truncate">{{ attachment.filename }}</span>
                                        </a>
                                    </div>
                                </div>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    class="shrink-0 text-destructive hover:text-destructive"
                                    :aria-label="t('projects.email.unlink')"
                                    @click="requestDelete(link.id)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>

                            <div
                                v-if="expandedLinkId === link.id"
                                class="border-t bg-muted/20 px-4 py-3 pl-14 text-sm"
                            >
                                <div
                                    v-if="bodyLoadingId === link.id"
                                    class="flex items-center gap-2 text-muted-foreground"
                                >
                                    <Loader2 class="size-4 animate-spin" />
                                    {{ t('projects.email.loading_body') }}
                                </div>
                                <pre
                                    v-if="bodies[link.id]?.text"
                                    class="whitespace-pre-wrap wrap-break-word font-sans text-sm"
                                >{{ bodies[link.id]?.text }}</pre>
                                <pre
                                    v-else-if="bodies[link.id]?.html"
                                    class="whitespace-pre-wrap wrap-break-word font-sans text-sm"
                                >{{ bodies[link.id]?.html?.replace(/<[^>]+>/g, ' ') }}</pre>
                                <p
                                    v-else-if="bodyLoadingId !== link.id"
                                    class="text-muted-foreground"
                                >
                                    {{ t('projects.email.no_body') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            :title="t('users.delete_confirm_title')"
            :description="deleteDialogDescription"
            @confirm="confirmDelete"
            @cancel="linkToDelete = null; threadLinkIdsToDelete = null; showDeleteDialog = false"
        />
    </div>
</template>
