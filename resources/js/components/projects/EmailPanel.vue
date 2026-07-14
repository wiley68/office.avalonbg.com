<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Loader2, MailPlus, RefreshCw, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAppToast } from '@/composables/useAppToast';
import { useTranslations } from '@/composables/useTranslations';
import { edit as editEmailSettings } from '@/routes/email';
import { destroy as destroyEmailLink, store as storeEmailLink } from '@/routes/projects/email';

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
    last_verified_at: string | null;
};

type BrowseMessage = {
    uid: number;
    uidvalidity: number;
    subject: string;
    from_name: string | null;
    from_address: string | null;
    sent_at: string | null;
};

type Props = {
    projectId: number;
    hasImapConfigured: boolean;
};

const props = defineProps<Props>();

const { t } = useTranslations();
const { showError, showMessage } = useAppToast();

const links = ref<EmailLink[]>([]);
const loading = ref(false);
const loadError = ref<string | null>(null);
const showBrowseDialog = ref(false);
const browseLoading = ref(false);
const browseMessages = ref<BrowseMessage[]>([]);
const browseFolder = ref('INBOX');
const browseSearch = ref('');
const expandedLinkId = ref<number | null>(null);
const bodyLoadingId = ref<number | null>(null);
const bodies = ref<Record<number, { text: string | null; html: string | null }>>({});
const linkToDelete = ref<number | null>(null);
const showDeleteDialog = ref(false);

const filteredBrowseMessages = computed(() => {
    const query = browseSearch.value.trim().toLowerCase();

    if (query === '') {
        return browseMessages.value;
    }

    return browseMessages.value.filter(
        (message) =>
            message.subject.toLowerCase().includes(query)
            || (message.from_name ?? '').toLowerCase().includes(query)
            || (message.from_address ?? '').toLowerCase().includes(query),
    );
});

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
};

const fromLabel = (link: EmailLink | BrowseMessage): string => {
    if (link.from_name && link.from_address) {
        return `${link.from_name} <${link.from_address}>`;
    }

    return link.from_address ?? link.from_name ?? '—';
};

const fetchLinks = async (): Promise<void> => {
    if (!props.hasImapConfigured) {
        return;
    }

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
            data: { configured: boolean; links: EmailLink[] };
        };

        links.value = payload.data.links;
    } catch {
        loadError.value = t('projects.email.load_error');
        showError(t('common.error'), t('projects.email.load_error'));
    } finally {
        loading.value = false;
    }
};

const openBrowse = async (): Promise<void> => {
    showBrowseDialog.value = true;
    browseLoading.value = true;

    try {
        const response = await fetch('/internal-api/imap/messages?limit=50', {
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

        const payload = (await response.json()) as {
            data: BrowseMessage[];
            meta?: { folder?: string };
        };
        browseMessages.value = payload.data;
        browseFolder.value = payload.meta?.folder ?? 'INBOX';
    } catch {
        showError(t('common.error'), t('projects.email.browse_error'));
    } finally {
        browseLoading.value = false;
    }
};

const linkMessage = (message: BrowseMessage): void => {
    router.post(
        storeEmailLink(props.projectId).url,
        {
            folder: browseFolder.value,
            imap_uid: message.uid,
            uidvalidity: message.uidvalidity,
            subject: message.subject,
            from_name: message.from_name,
            from_address: message.from_address,
            sent_at: message.sent_at,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showBrowseDialog.value = false;
                showMessage(t('common.success'), t('projects.email.linked'));
                fetchLinks();
            },
            onError: (errors) => {
                showError(t('common.error'), Object.values(errors).flat().join('\n'));
            },
        },
    );
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
    linkToDelete.value = linkId;
    showDeleteDialog.value = true;
};

const confirmDelete = (): void => {
    if (linkToDelete.value === null) {
        return;
    }

    const linkId = linkToDelete.value;
    linkToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(destroyEmailLink({ project: props.projectId, emailLink: linkId }).url, {
        preserveScroll: true,
        onSuccess: () => fetchLinks(),
    });
};

onMounted(() => {
    if (props.hasImapConfigured) {
        fetchLinks();
    }
});
</script>

<template>
    <div class="space-y-6">
        <div
            v-if="!hasImapConfigured"
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
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    @click="openBrowse"
                                >
                                    <MailPlus class="mr-2 h-4 w-4" />
                                    {{ t('projects.email.link_message') }}
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
                                    @click="fetchLinks"
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
                v-if="loading && links.length === 0"
                class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                <Loader2 class="mx-auto mb-2 size-5 animate-spin" />
                {{ t('common.table.loading') }}
            </div>

            <div
                v-else-if="loadError && links.length === 0"
                class="rounded-md border border-dashed p-6 text-center text-sm text-destructive"
            >
                {{ loadError }}
            </div>

            <div
                v-else-if="links.length === 0"
                class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                {{ t('projects.email.empty') }}
            </div>

            <div v-else class="space-y-2">
                <div
                    v-for="link in links"
                    :key="link.id"
                    class="rounded-md border"
                    :class="{
                        'opacity-70': link.status === 'missing_on_server',
                    }"
                >
                    <div class="flex items-start gap-3 p-3">
                        <button
                            type="button"
                            class="min-w-0 flex-1 text-left"
                            :class="{
                                'cursor-pointer hover:opacity-80': link.status === 'active',
                                'cursor-not-allowed': link.status === 'missing_on_server',
                            }"
                            @click="toggleBody(link)"
                        >
                            <p
                                class="font-medium"
                                :class="{
                                    'line-through': link.status === 'missing_on_server',
                                }"
                            >
                                {{ link.subject || t('projects.email.no_subject') }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ fromLabel(link) }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ formatDateTime(link.sent_at) }}
                                <span v-if="link.status === 'missing_on_server'">
                                    · {{ t('projects.email.missing_on_server') }}
                                </span>
                            </p>
                        </button>
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
                        class="border-t bg-muted/20 p-3 text-sm"
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
                        <p v-else class="text-muted-foreground">
                            {{ t('projects.email.no_body') }}
                        </p>
                    </div>
                </div>
            </div>
        </template>

        <Dialog v-model:open="showBrowseDialog">
            <DialogContent class="max-h-[80vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{{ t('projects.email.browse_title') }}</DialogTitle>
                </DialogHeader>

                <Input
                    v-model="browseSearch"
                    type="search"
                    :placeholder="t('projects.email.browse_search')"
                />

                <div
                    v-if="browseLoading"
                    class="py-8 text-center text-sm text-muted-foreground"
                >
                    <Loader2 class="mx-auto mb-2 size-5 animate-spin" />
                    {{ t('common.table.loading') }}
                </div>

                <div
                    v-else-if="filteredBrowseMessages.length === 0"
                    class="py-8 text-center text-sm text-muted-foreground"
                >
                    {{ t('projects.email.browse_empty') }}
                </div>

                <div v-else class="space-y-2">
                    <div
                        v-for="message in filteredBrowseMessages"
                        :key="message.uid"
                        class="flex items-start justify-between gap-3 rounded-md border p-3"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ message.subject }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ fromLabel(message) }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ formatDateTime(message.sent_at) }}
                            </p>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            @click="linkMessage(message)"
                        >
                            {{ t('projects.email.link') }}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            :title="t('users.delete_confirm_title')"
            :description="t('projects.email.unlink_confirm')"
            @confirm="confirmDelete"
            @cancel="linkToDelete = null; showDeleteDialog = false"
        />
    </div>
</template>
