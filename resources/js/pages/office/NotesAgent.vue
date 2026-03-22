<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import AgentChatPanel from '@/components/AgentChatPanel.vue';
import NotesManualPanel from '@/components/NotesManualPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import dashboardRoutes from '@/routes/dashboard';
import type { BreadcrumbItem } from '@/types';

const messagesUrl = (id: string) =>
    dashboardRoutes.notes.agent.conversation.messages.url(id);
const feedbackUrl = (id: string) =>
    dashboardRoutes.notes.agent.message.feedback.url(id);
const emailUrl = (id: string) =>
    dashboardRoutes.notes.agent.message.email.url(id);
const pdfUrl = (id: string) => dashboardRoutes.notes.agent.message.pdf.url(id);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Композитор',
        href: dashboard(),
    },
    {
        title: 'Бележки',
        href: dashboardRoutes.notes.url(),
    },
];

const VIEW_MODE_KEY = 'office-notes-view-mode';

type ViewMode = 'agent' | 'manual';

const viewMode = ref<ViewMode>('agent');

onMounted(() => {
    const stored = sessionStorage.getItem(VIEW_MODE_KEY);

    if (stored === 'manual' || stored === 'agent') {
        viewMode.value = stored;
    }
});

watch(viewMode, (v) => {
    sessionStorage.setItem(VIEW_MODE_KEY, v);
});

const pageDescription = computed(() =>
    viewMode.value === 'agent'
        ? 'Тук работи агентът за вашите лични бележки (notes). Историята на разговора се пази на сървъра; „Нов разговор“ започва изчистен контекст.'
        : 'Ръчно управление на бележките без агент — директно към базата, без разход за AI токени.',
);
</script>

<template>
    <Head title="Агент — бележки" />

    <AppLayout
        :breadcrumbs="breadcrumbs"
        page-title="Агент за бележки"
        :page-description="pageDescription"
    >
        <template #pageActions>
            <div
                class="inline-flex rounded-md border border-input bg-background p-0.5 shadow-xs"
                role="group"
                aria-label="Режим на бележки"
            >
                <button
                    type="button"
                    :class="[
                        'rounded px-2.5 py-1 text-xs font-medium transition-colors',
                        viewMode === 'agent'
                            ? 'bg-muted text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                    @click="viewMode = 'agent'"
                >
                    Агент
                </button>
                <button
                    type="button"
                    :class="[
                        'rounded px-2.5 py-1 text-xs font-medium transition-colors',
                        viewMode === 'manual'
                            ? 'bg-muted text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                    @click="viewMode = 'manual'"
                >
                    Ръчно
                </button>
            </div>
        </template>

        <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
            <div
                v-show="viewMode === 'agent'"
                class="flex min-h-0 flex-1 flex-col overflow-hidden"
            >
                <AgentChatPanel
                    :post-url="dashboardRoutes.notes.agent.url()"
                    :messages-url="messagesUrl"
                    :conversations-url="
                        dashboardRoutes.notes.agent.conversations.url()
                    "
                    :delete-all-conversations-url="
                        dashboardRoutes.notes.agent.conversations.destroy.url()
                    "
                    :feedback-url="feedbackUrl"
                    :email-url="emailUrl"
                    :pdf-url="pdfUrl"
                    session-key="office-notes-agent"
                    textarea-id="notes-agent-message"
                    placeholder="Вашата заявка, например: Покажи ми бележките ми. / Създай бележка „Среща“ с описание …"
                />
            </div>
            <NotesManualPanel
                v-show="viewMode === 'manual'"
                :active="viewMode === 'manual'"
            />
        </div>
    </AppLayout>
</template>
