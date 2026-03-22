<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AgentChatPanel from '@/components/AgentChatPanel.vue';
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

const pageDescription =
    'Тук работи само агентът за вашите лични бележки (notes). Историята на разговора се пази на сървъра; „Нов разговор“ започва изчистен контекст.';
</script>

<template>
    <Head title="Агент — бележки" />

    <AppLayout
        :breadcrumbs="breadcrumbs"
        page-title="Агент за бележки"
        :page-description="pageDescription"
    >
        <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
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
    </AppLayout>
</template>
