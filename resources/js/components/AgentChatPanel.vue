<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { consumeAgentSseStream } from '@/composables/useAgentSse';

export type ChatMessage = {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    created_at: string;
    feedback?: 'up' | 'down' | null;
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
    /** POST JSON feedback за assistant съобщение */
    feedbackUrl: (messageId: string) => string;
    /** POST JSON изпращане на assistant съобщение по имейл */
    emailUrl: (messageId: string) => string;
    /** GET PDF на assistant съобщение (отваряне в нов раздел) */
    pdfUrl: (messageId: string) => string;
    placeholder?: string;
    textareaId?: string;
    sessionKey?: string;
};

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Напишете заявката си…',
    textareaId: 'agent-message',
    sessionKey: '',
});

const textareaPlaceholder = computed(
    () =>
        `${props.placeholder}\nEnter за изпращане · Ctrl+Enter или ⌘+Enter за нов ред`,
);

const message = ref('');
const messages = ref<ChatMessage[]>([]);
const streamingAssistant = ref<string | null>(null);
const copiedMessageId = ref<string | null>(null);
const emailedMessageId = ref<string | null>(null);
const emailDialogOpen = ref(false);
const emailTargetMessageId = ref<string | null>(null);
const emailTargetMessageContent = ref<string>('');
const emailRecipient = ref('');
const emailSubject = ref('Отговор от офис агента');
const error = ref<string | null>(null);
const validationErrors = ref<string[]>([]);
const sending = ref(false);
const conversationId = ref<string | null>(null);
const historyEl = ref<HTMLElement | null>(null);
const messageInputEl = ref<HTMLTextAreaElement | null>(null);

const conversations = ref<AgentConversationSummary[]>([]);
const conversationsLoading = ref(false);
const conversationsError = ref<string | null>(null);

const storageId = () =>
    props.sessionKey.length > 0 ? props.sessionKey : `conv:${props.postUrl}`;
const emailStorageId = () =>
    props.sessionKey.length > 0
        ? `agent-email:${props.sessionKey}`
        : `agent-email:${props.postUrl}`;

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

const focusMessageInput = (): void => {
    messageInputEl.value?.focus({ preventScroll: true });
};

onMounted(() => {
    const stored = sessionStorage.getItem(storageId());
    const storedEmail = localStorage.getItem(emailStorageId());

    if (stored) {
        conversationId.value = stored;
    }

    if (storedEmail) {
        emailRecipient.value = storedEmail;
    }

    void loadConversations();

    void nextTick(() => {
        focusMessageInput();
    });
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
        void nextTick(() => {
            focusMessageInput();
        });

        return;
    }

    conversationId.value = id;

    void nextTick(() => {
        focusMessageInput();
    });
};

const startNewConversation = (): void => {
    conversationId.value = null;
    messages.value = [];
    streamingAssistant.value = null;
    error.value = null;
    validationErrors.value = [];

    void nextTick(() => {
        focusMessageInput();
    });
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

const onMessageKeydown = (e: KeyboardEvent): void => {
    if (e.key !== 'Enter') {
        return;
    }

    if (e.ctrlKey || e.metaKey) {
        e.preventDefault();
        const ta = e.target as HTMLTextAreaElement;
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const val = message.value;
        message.value = val.slice(0, start) + '\n' + val.slice(end);
        void nextTick(() => {
            ta.selectionStart = ta.selectionEnd = start + 1;
        });

        return;
    }

    if (e.shiftKey) {
        return;
    }

    e.preventDefault();
    void submit();
};

const copyAssistantMessage = async (
    messageId: string,
    content: string,
): Promise<void> => {
    try {
        if (
            typeof navigator !== 'undefined' &&
            navigator.clipboard &&
            typeof navigator.clipboard.writeText === 'function'
        ) {
            await navigator.clipboard.writeText(content);
        } else {
            const input = document.createElement('textarea');
            input.value = content;
            input.style.position = 'fixed';
            input.style.opacity = '0';
            document.body.appendChild(input);
            input.focus();
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
        }

        copiedMessageId.value = messageId;
        window.setTimeout(() => {
            if (copiedMessageId.value === messageId) {
                copiedMessageId.value = null;
            }
        }, 1400);
    } catch {
        error.value = 'Неуспешно копиране в клипборда.';
    }
};

const rateAssistantMessage = async (
    messageId: string,
    feedback: 'up' | 'down',
): Promise<void> => {
    try {
        const response = await fetch(props.feedbackUrl(messageId), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ feedback }),
        });

        if (!response.ok) {
            error.value = `Грешка ${response.status}. Неуспешно запазване на оценката.`;

            return;
        }

        messages.value = messages.value.map((item) =>
            item.id === messageId ? { ...item, feedback } : item,
        );
    } catch (e) {
        error.value =
            e instanceof Error ? e.message : 'Неуспешно запазване на оценката.';
    }
};

const openAssistantPdf = (messageId: string): void => {
    window.open(props.pdfUrl(messageId), '_blank', 'noopener,noreferrer');
};

const openEmailDialog = (messageId: string, content: string): void => {
    emailTargetMessageId.value = messageId;
    emailTargetMessageContent.value = content;
    emailSubject.value = 'Отговор от офис агента';
    emailDialogOpen.value = true;
};

const sendAssistantMessageByEmail = async (): Promise<void> => {
    if (
        !emailTargetMessageId.value ||
        emailRecipient.value.trim().length === 0
    ) {
        return;
    }

    try {
        const response = await fetch(
            props.emailUrl(emailTargetMessageId.value),
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    email: emailRecipient.value.trim(),
                    subject: emailSubject.value.trim() || undefined,
                }),
            },
        );

        if (!response.ok) {
            const contentType = response.headers.get('Content-Type') ?? '';

            if (contentType.includes('application/json')) {
                const data = (await response.json()) as {
                    message?: string;
                    errors?: Record<string, string[]>;
                };
                const validationMessage = data.errors
                    ? Object.values(data.errors).flat()[0]
                    : null;
                error.value =
                    validationMessage ??
                    data.message ??
                    `Грешка ${response.status}. Неуспешно изпращане на имейл.`;
            } else {
                error.value = `Грешка ${response.status}. Неуспешно изпращане на имейл.`;
            }

            return;
        }

        emailedMessageId.value = emailTargetMessageId.value;
        localStorage.setItem(emailStorageId(), emailRecipient.value.trim());
        window.setTimeout(() => {
            if (emailedMessageId.value === emailTargetMessageId.value) {
                emailedMessageId.value = null;
            }
        }, 1600);

        emailDialogOpen.value = false;
    } catch (e) {
        error.value =
            e instanceof Error ? e.message : 'Неуспешно изпращане на имейл.';
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
    <div class="flex h-full min-h-0 w-full flex-1 flex-col gap-0 lg:flex-row">
        <!-- Ляв сайдбар: история на разговорите -->
        <aside
            class="flex max-h-[min(280px,40vh)] shrink-0 flex-col border-b border-sidebar-border/70 bg-muted/15 lg:max-h-none lg:min-h-0 lg:w-72 lg:self-stretch lg:border-r lg:border-b-0 dark:border-sidebar-border"
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
                                    ? 'border-sidebar-border/60 bg-muted/50 text-foreground dark:border-sidebar-border/80 dark:bg-muted/30'
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

        <!-- Дясно: текущ чат — история скролира; полето за въпрос е закачено долу -->
        <div
            class="mx-auto flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden p-4 md:p-6 lg:max-w-none lg:p-8"
        >
            <div
                class="flex min-h-0 w-full max-w-3xl flex-1 flex-col self-center overflow-hidden"
            >
                <div
                    ref="historyEl"
                    class="min-h-0 flex-1 overflow-x-hidden overflow-y-auto p-3"
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
                        class="flex w-full flex-col"
                        :class="m.role === 'user' ? 'items-end' : 'items-start'"
                    >
                        <div
                            class="max-w-[85%] rounded-lg px-3 py-2 text-sm wrap-anywhere whitespace-pre-wrap"
                            :class="
                                m.role === 'user'
                                    ? 'bg-muted text-foreground'
                                    : 'text-foreground'
                            "
                        >
                            {{ m.content }}
                        </div>
                        <div
                            v-if="m.role === 'assistant'"
                            class="mt-1 flex w-full max-w-[85%] items-center gap-1"
                        >
                            <TooltipProvider :delay-duration="0">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded-md p-1 text-muted-foreground transition hover:bg-muted/60 hover:text-foreground focus-visible:ring-2 focus-visible:ring-muted-foreground/30 focus-visible:outline-none"
                                            :aria-label="
                                                copiedMessageId === m.id
                                                    ? 'Копирано'
                                                    : 'Копирай отговора'
                                            "
                                            @click="
                                                copyAssistantMessage(
                                                    m.id,
                                                    m.content,
                                                )
                                            "
                                        >
                                            <svg
                                                class="size-5"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                aria-hidden="true"
                                            >
                                                <rect
                                                    x="9"
                                                    y="9"
                                                    width="13"
                                                    height="13"
                                                    rx="2"
                                                    ry="2"
                                                />
                                                <path
                                                    d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"
                                                />
                                            </svg>
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Копирай отговора</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                            <TooltipProvider :delay-duration="0">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded-md p-1 text-muted-foreground transition hover:bg-muted/60 hover:text-foreground focus-visible:ring-2 focus-visible:ring-muted-foreground/30 focus-visible:outline-none"
                                            :class="
                                                emailedMessageId === m.id
                                                    ? 'text-foreground'
                                                    : ''
                                            "
                                            :aria-label="
                                                emailedMessageId === m.id
                                                    ? 'Изпратено'
                                                    : 'Изпрати по имейл'
                                            "
                                            @click="
                                                openEmailDialog(m.id, m.content)
                                            "
                                        >
                                            <svg
                                                class="size-5"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                aria-hidden="true"
                                            >
                                                <rect
                                                    x="3"
                                                    y="5"
                                                    width="18"
                                                    height="14"
                                                    rx="2"
                                                />
                                                <path d="m3 7 9 6 9-6" />
                                            </svg>
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Изпрати по имейл</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                            <TooltipProvider :delay-duration="0">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded-md p-1 text-muted-foreground transition hover:bg-muted/60 hover:text-foreground focus-visible:ring-2 focus-visible:ring-muted-foreground/30 focus-visible:outline-none"
                                            aria-label="Отвори като PDF"
                                            @click="openAssistantPdf(m.id)"
                                        >
                                            <svg
                                                class="size-5"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"
                                                />
                                                <path d="M14 2v6h6" />
                                                <path d="M10 12h4" />
                                                <path d="M10 16h4" />
                                                <path d="M10 8h1" />
                                            </svg>
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Отвори като PDF</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                            <TooltipProvider :delay-duration="0">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded-md p-1 text-muted-foreground transition hover:bg-muted/60 hover:text-foreground focus-visible:ring-2 focus-visible:ring-muted-foreground/30 focus-visible:outline-none"
                                            :class="
                                                m.feedback === 'up'
                                                    ? 'text-foreground'
                                                    : ''
                                            "
                                            aria-label="Полезен отговор"
                                            @click="
                                                rateAssistantMessage(m.id, 'up')
                                            "
                                        >
                                            <svg
                                                class="size-5"
                                                viewBox="0 0 24 24"
                                                fill="currentColor"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    d="M5,9V21H1V9H5M9,21A2,2 0 0,1 7,19V9C7,8.45 7.22,7.95 7.59,7.59L14.17,1L15.23,2.06C15.5,2.33 15.67,2.7 15.67,3.11L15.64,3.43L14.69,8H21C22.11,8 23,8.9 23,10V12C23,12.26 22.95,12.5 22.86,12.73L19.84,19.78C19.54,20.5 18.83,21 18,21H9M9,19H18.03L21,12V10H12.21L13.34,4.68L9,9.03V19Z"
                                                />
                                            </svg>
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Полезен отговор</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                            <TooltipProvider :delay-duration="0">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded-md p-1 text-muted-foreground transition hover:bg-muted/60 hover:text-foreground focus-visible:ring-2 focus-visible:ring-muted-foreground/30 focus-visible:outline-none"
                                            :class="
                                                m.feedback === 'down'
                                                    ? 'text-foreground'
                                                    : ''
                                            "
                                            aria-label="Неполезен отговор"
                                            @click="
                                                rateAssistantMessage(
                                                    m.id,
                                                    'down',
                                                )
                                            "
                                        >
                                            <svg
                                                class="size-5"
                                                viewBox="0 0 24 24"
                                                fill="currentColor"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    d="M19,15V3H23V15H19M15,3A2,2 0 0,1 17,5V15C17,15.55 16.78,16.05 16.41,16.41L9.83,23L8.77,21.94C8.5,21.67 8.33,21.3 8.33,20.88L8.36,20.57L9.31,16H3C1.89,16 1,15.1 1,14V12C1,11.74 1.05,11.5 1.14,11.27L4.16,4.22C4.46,3.5 5.17,3 6,3H15M15,5H5.97L3,12V14H11.78L10.65,19.32L15,14.97V5Z"
                                                />
                                            </svg>
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Неполезен отговор</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                    </div>
                    <div
                        v-if="streamingAssistant !== null"
                        class="flex w-full flex-col items-start gap-1"
                    >
                        <div
                            class="max-w-[85%] px-3 py-2 text-sm wrap-anywhere whitespace-pre-wrap text-foreground"
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

            <div
                class="flex w-full max-w-3xl shrink-0 flex-col gap-2 self-center border-t border-sidebar-border/50 bg-background pt-3 dark:border-sidebar-border/60"
            >
                <div
                    v-if="validationErrors.length"
                    class="w-full rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                >
                    <ul class="list-inside list-disc space-y-1">
                        <li v-for="(err, i) in validationErrors" :key="i">
                            {{ err }}
                        </li>
                    </ul>
                </div>

                <div
                    v-if="error"
                    class="w-full rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                >
                    {{ error }}
                </div>

                <textarea
                    ref="messageInputEl"
                    :id="textareaId"
                    v-model="message"
                    rows="3"
                    class="min-h-[72px] w-full resize-y rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-muted-foreground/30 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    :placeholder="textareaPlaceholder"
                    :disabled="sending"
                    aria-label="Поле за съобщение към агента"
                    autocomplete="off"
                    @keydown="onMessageKeydown"
                />
            </div>

            <Dialog
                :open="emailDialogOpen"
                @update:open="emailDialogOpen = $event"
            >
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Изпрати отговора по имейл</DialogTitle>
                        <DialogDescription>
                            Избраният assistant отговор ще бъде изпратен на
                            посочения имейл адрес.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4">
                        <div class="space-y-2">
                            <Label for="agent-email-recipient"
                                >Имейл адрес</Label
                            >
                            <Input
                                id="agent-email-recipient"
                                v-model="emailRecipient"
                                type="email"
                                placeholder="name@example.com"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="agent-email-subject"
                                >Тема (по избор)</Label
                            >
                            <Input
                                id="agent-email-subject"
                                v-model="emailSubject"
                                type="text"
                                placeholder="Отговор от офис агента"
                            />
                        </div>

                        <div
                            v-if="emailTargetMessageContent"
                            class="rounded-md border border-sidebar-border/60 bg-muted/20 px-3 py-2 text-xs wrap-anywhere whitespace-pre-wrap text-muted-foreground"
                        >
                            {{ emailTargetMessageContent }}
                        </div>
                    </div>

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary">Отказ</Button>
                        </DialogClose>
                        <Button
                            type="button"
                            :disabled="emailRecipient.trim().length === 0"
                            @click="sendAssistantMessageByEmail"
                        >
                            Изпрати
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
