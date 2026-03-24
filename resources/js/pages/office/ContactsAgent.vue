<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import AgentChatPanel from '@/components/AgentChatPanel.vue';
import ContactsManualPanel from '@/components/ContactsManualPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import dashboardRoutes from '@/routes/dashboard';
import type { BreadcrumbItem } from '@/types';

const messagesUrl = (id: string) =>
    dashboardRoutes.contacts.agent.conversation.messages.url(id);
const feedbackUrl = (id: string) =>
    dashboardRoutes.contacts.agent.message.feedback.url(id);
const emailUrl = (id: string) =>
    dashboardRoutes.contacts.agent.message.email.url(id);
const pdfUrl = (id: string) =>
    dashboardRoutes.contacts.agent.message.pdf.url(id);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Композитор',
        href: dashboard(),
    },
    {
        title: 'Контакти',
        href: dashboardRoutes.contacts.url(),
    },
];

const VIEW_MODE_KEY = 'office-contacts-view-mode';

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
        ? 'Работи с contacts от service базата чрез агент. Историята на разговора се пази на сървъра; „Нов разговор“ започва изчистен контекст.'
        : 'Ръчно управление на contacts без агент — директно към service базата.',
);
</script>

<template>
    <Head title="Агент — контакти" />

    <AppLayout
        :breadcrumbs="breadcrumbs"
        page-title="Агент за контакти"
        :page-description="pageDescription"
    >
        <template #pageActions>
            <div class="flex h-full items-center gap-2 pl-2">
                <div class="h-6 w-px bg-gray-200 dark:bg-gray-700"></div>
                <div
                    class="inline-flex rounded-md border border-input bg-background p-0.5 shadow-xs"
                    role="group"
                    aria-label="Режим на контакти"
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
            </div>
        </template>

        <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
            <div
                v-show="viewMode === 'agent'"
                class="flex min-h-0 flex-1 flex-col overflow-hidden"
            >
                <AgentChatPanel
                    :post-url="dashboardRoutes.contacts.agent.url()"
                    :messages-url="messagesUrl"
                    :conversations-url="
                        dashboardRoutes.contacts.agent.conversations.url()
                    "
                    :delete-all-conversations-url="
                        dashboardRoutes.contacts.agent.conversations.destroy.url()
                    "
                    :feedback-url="feedbackUrl"
                    :email-url="emailUrl"
                    :pdf-url="pdfUrl"
                    session-key="office-contacts-agent"
                    textarea-id="contacts-agent-message"
                    placeholder="Например: Покажи контакт с id 10. / Създай контакт за Иван Петров в София..."
                />
            </div>
            <ContactsManualPanel
                v-show="viewMode === 'manual'"
                :active="viewMode === 'manual'"
            />
        </div>
    </AppLayout>
</template>
