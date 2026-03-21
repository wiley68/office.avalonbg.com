<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue';
import { consumeAgentSseStream } from '@/composables/useAgentSse';

export type ChatMessage = {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    created_at: string;
};

export type AgentConversationSummary = {
    id: string;
    title: string;
    updated_at: string;
};

type Props = {
    postUrl: string;
    /** GET JSON с история за даден conversation id */
    messagesUrl: (conversationId: string) => string;
    /** GET JSON списък с разговори (id, title, updated_at) */
    conversationsUrl: string;
    title: string;
    description: string;
    placeholder?: string;
    textareaId?: string;
    submitLabel?: string;
    sessionKey?: string;
};

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Напишете заявката си…',
    textareaId: 'agent-message',
    submitLabel: 'Изпрати към агента',
    sessionKey: '',
});

const message = ref('');
const messages = ref<ChatMessage[]>([]);
const streamingAssistant = ref<string | null>(null);
const error = ref<string | null>(null);
const validationErrors = ref<string[]>([]);
const sending = ref(false);
const conversationId = ref<string | null>(null);
const historyEl = ref<HTMLElement | null>(null);

const conversations = ref<AgentConversationSummary[]>([]);
const conversationsLoading = ref(false);
const conversationsError = ref<string | null>(null);

const storageId = () =>
    props.sessionKey.length > 0 ? props.sessionKey : `conv:${props.postUrl}`;

const canSend = () => message.value.trim().length > 0 && !sending.value;

const csrfToken = (): string =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

const scrollHistoryToBottom = async (): Promise<void> => {
    await nextTick();
    const el = historyEl.value;

    if (el) {
        el.scrollTop = el.scrollHeight;
    }
};

const loadConversations = async (): Promise<void> => {
    conversationsLoading.value = true;
    conversationsError.value = null;

    try {
        const response = await fetch(props.conversationsUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            conversationsError.value =
                'Неуспешно зареждане на списъка с разговори.';

            return;
        }

        const data = (await response.json()) as {
            conversations?: AgentConversationSummary[];
        };
        conversations.value = data.conversations ?? [];
    } catch {
        conversationsError.value =
            'Неуспешно зареждане на списъка с разговори.';
    } finally {
        conversationsLoading.value = false;
    }
};

const loadHistory = async (): Promise<void> => {
    if (!conversationId.value) {
        messages.value = [];

        return;
    }

    try {
        const response = await fetch(props.messagesUrl(conversationId.value), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const data = (await response.json()) as { messages: ChatMessage[] };
        messages.value = data.messages ?? [];
        await scrollHistoryToBottom();
    } catch {
        //
    }
};

onMounted(() => {
    const stored = sessionStorage.getItem(storageId());

    if (stored) {
        conversationId.value = stored;
    }

    void loadConversations();
});

watch(conversationId, (id) => {
    if (id) {
        sessionStorage.setItem(storageId(), id);
    } else {
        sessionStorage.removeItem(storageId());
    }

    void loadHistory();
});

const selectConversation = (id: string): void => {
    if (conversationId.value === id) {
        return;
    }

    conversationId.value = id;
};

const startNewConversation = (): void => {
    conversationId.value = null;
    messages.value = [];
    streamingAssistant.value = null;
    error.value = null;
    validationErrors.value = [];
};

const formatTime = (iso: string): string => {
    try {
        return new Date(iso).toLocaleString('bg-BG', {
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return '';
    }
};

const formatSidebarDate = (iso: string): string => {
    try {
        return new Date(iso).toLocaleDateString('bg-BG', {
            day: '2-digit',
            month: 'short',
        });
    } catch {
        return '';
    }
};

const submit = async (): Promise<void> => {
    if (!canSend()) {
        return;
    }

    error.value = null;
    validationErrors.value = [];
    sending.value = true;
    streamingAssistant.value = '';

    try {
        const payload: Record<string, string> = {
            message: message.value,
        };

        if (conversationId.value) {
            payload.conversation_id = conversationId.value;
        }

        const response = await fetch(props.postUrl, {
            method: 'POST',
            headers: {
                Accept: 'text/event-stream',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        const contentType = response.headers.get('Content-Type') ?? '';

        if (
            response.status === 422 &&
            contentType.includes('application/json')
        ) {
            const data = (await response.json()) as {
                errors?: Record<string, string[]>;
            };

            if (data.errors) {
                validationErrors.value = Object.values(data.errors).flat();
            }

            streamingAssistant.value = null;

            return;
        }

        if (!response.ok && contentType.includes('application/json')) {
            const data = (await response.json()) as {
                message?: string;
                error?: string | null;
            };

            error.value =
                data.message ??
                data.error ??
                `Грешка ${response.status}. Опитайте отново.`;
            streamingAssistant.value = null;

            return;
        }

        if (!response.ok || !response.body) {
            error.value = `Грешка ${response.status}. Опитайте отново.`;
            streamingAssistant.value = null;

            return;
        }

        await consumeAgentSseStream(
            response.body,
            (delta) => {
                streamingAssistant.value =
                    (streamingAssistant.value ?? '') + delta;
                void scrollHistoryToBottom();
            },
            (id) => {
                if (typeof id === 'string' && id.length > 0) {
                    conversationId.value = id;
                }
            },
        );

        streamingAssistant.value = null;
        await loadHistory();
        await loadConversations();
        message.value = '';
    } catch (e) {
        error.value =
            e instanceof Error ? e.message : 'Неуспешна заявка към сървъра.';
        streamingAssistant.value = null;
    } finally {
        sending.value = false;
    }
};
</script>

<template>
    <div
        class="flex min-h-0 w-full flex-1 flex-col gap-0 lg:min-h-[calc(100svh-9rem)] lg:flex-row"
    >
        <!-- Ляв сайдбар: история на разговорите -->
        <aside
            class="flex max-h-[min(280px,40vh)] shrink-0 flex-col border-b border-sidebar-border/70 bg-muted/15 lg:max-h-none lg:w-72 lg:border-r lg:border-b-0 dark:border-sidebar-border"
        >
            <div
                class="flex shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/50 px-3 py-3 dark:border-sidebar-border/80"
            >
                <h2 class="text-sm font-semibold text-foreground">Разговори</h2>
                <button
                    type="button"
                    class="shrink-0 rounded-md border border-input bg-background px-2 py-1 text-xs text-foreground shadow-sm transition hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :disabled="sending"
                    @click="startNewConversation"
                >
                    Нов
                </button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto px-2 py-2">
                <p
                    v-if="conversationsLoading"
                    class="px-2 py-3 text-center text-xs text-muted-foreground"
                >
                    Зареждане…
                </p>
                <p
                    v-else-if="conversationsError"
                    class="rounded-md border border-destructive/30 bg-destructive/10 px-2 py-2 text-xs text-destructive"
                >
                    {{ conversationsError }}
                </p>
                <p
                    v-else-if="conversations.length === 0"
                    class="px-2 py-3 text-center text-xs text-muted-foreground"
                >
                    Няма запазени разговори. Изпратете съобщение вдясно.
                </p>
                <ul v-else class="flex flex-col gap-1">
                    <li v-for="c in conversations" :key="c.id">
                        <button
                            type="button"
                            class="flex w-full flex-col items-start gap-0.5 rounded-lg border px-2.5 py-2 text-left text-sm transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :class="
                                conversationId === c.id
                                    ? 'border-primary/50 bg-primary/10 text-foreground'
                                    : 'border-transparent bg-transparent text-foreground hover:bg-muted/50'
                            "
                            @click="selectConversation(c.id)"
                        >
                            <span class="line-clamp-2 w-full font-medium">{{
                                c.title
                            }}</span>
                            <span class="text-[10px] text-muted-foreground">{{
                                formatSidebarDate(c.updated_at)
                            }}</span>
                        </button>
                    </li>
                </ul>
            </div>
        </aside>

        <!-- Дясно: текущ чат -->
        <div
            class="mx-auto flex min-h-0 min-w-0 flex-1 flex-col gap-6 p-4 md:p-6 lg:max-w-none lg:p-8"
        >
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <h1
                        class="text-2xl font-semibold tracking-tight text-foreground"
                    >
                        {{ title }}
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ description }}
                    </p>
                    <p
                        v-if="conversationId"
                        class="mt-2 font-mono text-xs text-muted-foreground"
                    >
                        Разговор:
                        {{ conversationId.slice(0, 8) }}… (памет в сървъра)
                    </p>
                </div>
            </div>

            <div class="flex min-h-0 flex-1 flex-col gap-2">
                <h2 class="shrink-0 text-sm font-medium text-foreground">
                    История
                </h2>
                <div
                    ref="historyEl"
                    class="flex min-h-[min(280px,35vh)] flex-1 flex-col gap-3 overflow-y-auto rounded-lg border border-sidebar-border/70 bg-muted/20 p-3 lg:min-h-0 dark:border-sidebar-border"
                >
                    <p
                        v-if="
                            messages.length === 0 &&
                            streamingAssistant === null &&
                            !sending
                        "
                        class="text-center text-sm text-muted-foreground"
                    >
                        Няма съобщения. Изпратете заявка по-долу.
                    </p>
                    <div
                        v-for="m in messages"
                        :key="m.id"
                        class="flex w-full flex-col gap-1"
                        :class="m.role === 'user' ? 'items-end' : 'items-start'"
                    >
                        <div
                            class="max-w-[85%] rounded-2xl px-3 py-2 text-sm whitespace-pre-wrap"
                            :class="
                                m.role === 'user'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'border border-sidebar-border/60 bg-background text-foreground dark:border-sidebar-border'
                            "
                        >
                            {{ m.content }}
                        </div>
                        <span class="text-[10px] text-muted-foreground">{{
                            formatTime(m.created_at)
                        }}</span>
                    </div>
                    <div
                        v-if="streamingAssistant !== null"
                        class="flex w-full flex-col items-start gap-1"
                    >
                        <div
                            class="max-w-[85%] rounded-2xl border border-dashed border-primary/40 bg-background px-3 py-2 text-sm whitespace-pre-wrap text-foreground"
                        >
                            {{ streamingAssistant }}
                            <span
                                v-if="sending"
                                class="ml-1 inline-block size-2 animate-pulse rounded-full bg-primary"
                            />
                        </div>
                        <span class="text-[10px] text-muted-foreground"
                            >Стрийминг…</span
                        >
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3">
                <label
                    :for="textareaId"
                    class="text-sm font-medium text-foreground"
                >
                    Вашата заявка
                </label>
                <textarea
                    :id="textareaId"
                    v-model="message"
                    rows="4"
                    class="min-h-[100px] w-full resize-y rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    :placeholder="placeholder"
                    :disabled="sending"
                    @keydown.ctrl.enter.prevent="submit"
                    @keydown.meta.enter.prevent="submit"
                />
                <p class="text-xs text-muted-foreground">
                    Ctrl+Enter или ⌘+Enter за изпращане.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
                    :disabled="!canSend()"
                    @click="submit"
                >
                    <span
                        v-if="sending"
                        class="mr-2 size-4 animate-spin rounded-full border-2 border-primary-foreground/30 border-t-primary-foreground"
                    />
                    {{ sending ? 'Изпращане…' : submitLabel }}
                </button>
            </div>

            <div
                v-if="validationErrors.length"
                class="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
            >
                <ul class="list-inside list-disc space-y-1">
                    <li v-for="(err, i) in validationErrors" :key="i">
                        {{ err }}
                    </li>
                </ul>
            </div>

            <div
                v-if="error"
                class="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
            >
                {{ error }}
            </div>
        </div>
    </div>
</template>
