<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import AgentChatPanel from '@/components/AgentChatPanel.vue';
import ServiceCardsManualPanel from '@/components/ServiceCardsManualPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const messagesUrl = (id: string) =>
    `/dashboard/service-cards/agent/conversations/${id}/messages`;
const feedbackUrl = (id: string) =>
    `/dashboard/service-cards/agent/messages/${id}/feedback`;
const emailUrl = (id: string) => `/dashboard/service-cards/agent/messages/${id}/email`;
const pdfUrl = (id: string) => `/dashboard/service-cards/agent/messages/${id}/pdf`;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Композитор',
        href: dashboard(),
    },
    {
        title: 'Сервизни карти',
        href: '/dashboard/service-cards',
    },
];

const VIEW_MODE_KEY = 'office-service-cards-view-mode';

type ViewMode = 'agent' | 'manual';

const viewMode = ref<ViewMode>('agent');

const agentChatPanelRef = ref<InstanceType<typeof AgentChatPanel> | null>(null);
const manualPanelRef = ref<InstanceType<typeof ServiceCardsManualPanel> | null>(
    null,
);

const focusForCurrentViewMode = (): void => {
    if (viewMode.value === 'agent') {
        agentChatPanelRef.value?.focusMessageInput();
    } else {
        manualPanelRef.value?.focusSearchQuery();
    }
};

onMounted(() => {
    const stored = sessionStorage.getItem(VIEW_MODE_KEY);
    const beforeRestore = viewMode.value;

    if (stored === 'manual' || stored === 'agent') {
        viewMode.value = stored;
    }

    if (viewMode.value === beforeRestore) {
        void nextTick(() => {
            focusForCurrentViewMode();
        });
    }
});

watch(viewMode, (v) => {
    sessionStorage.setItem(VIEW_MODE_KEY, v);
    void nextTick(() => {
        focusForCurrentViewMode();
    });
});

const pageDescription = computed(() =>
    viewMode.value === 'agent'
        ? 'Работа със сервизни карти (projects) от service базата чрез агент.'
        : 'Преглед и управление на сервизни карти в ръчен режим.',
);
</script>

<template>
    <Head title="Агент — сервизни карти" />

    <AppLayout
        :breadcrumbs="breadcrumbs"
        page-title="Сервизни карти"
        :page-description="pageDescription"
    >
        <template #pageActions>
            <div class="flex h-full items-center gap-2 pl-2">
                <div class="h-6 w-px bg-gray-200 dark:bg-gray-700"></div>
                <div
                    class="inline-flex rounded-md border border-input bg-background p-0.5 shadow-xs"
                    role="group"
                    aria-label="Режим на сервизни карти"
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
                    ref="agentChatPanelRef"
                    post-url="/dashboard/service-cards/agent"
                    :messages-url="messagesUrl"
                    conversations-url="/dashboard/service-cards/agent/conversations"
                    delete-all-conversations-url="/dashboard/service-cards/agent/conversations"
                    :feedback-url="feedbackUrl"
                    :email-url="emailUrl"
                    :pdf-url="pdfUrl"
                    session-key="office-service-cards-agent"
                    textarea-id="service-cards-agent-message"
                    placeholder="Например: Покажи сервизна карта #12. / Колко сервизни карти общо има?"
                />
            </div>
            <ServiceCardsManualPanel
                ref="manualPanelRef"
                v-show="viewMode === 'manual'"
                :active="viewMode === 'manual'"
            />
        </div>
    </AppLayout>
</template>
