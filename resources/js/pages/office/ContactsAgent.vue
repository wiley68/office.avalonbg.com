<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AgentChatPanel from '@/components/AgentChatPanel.vue';
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
</script>

<template>
    <Head title="Агент — контакти" />

    <AppLayout
        :breadcrumbs="breadcrumbs"
        page-title="Агент за контакти"
        page-description="Работи с contacts от service базата. Историята на разговора се пази на сървъра; „Нов разговор“ започва изчистен контекст."
    >
        <AgentChatPanel
            :post-url="dashboardRoutes.contacts.agent.url()"
            :messages-url="messagesUrl"
            :conversations-url="dashboardRoutes.contacts.agent.conversations.url()"
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
    </AppLayout>
</template>
