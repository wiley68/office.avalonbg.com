<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AgentChatPanel from '@/components/AgentChatPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import dashboardRoutes from '@/routes/dashboard';
import type { BreadcrumbItem } from '@/types';

const messagesUrl = (id: string) =>
    dashboardRoutes.notes.agent.conversation.messages.url(id);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Табло',
        href: dashboard(),
    },
    {
        title: 'Бележки',
        href: dashboardRoutes.notes.url(),
    },
];
</script>

<template>
    <Head title="Агент — бележки" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <AgentChatPanel
            :post-url="dashboardRoutes.notes.agent.url()"
            :messages-url="messagesUrl"
            session-key="office-notes-agent"
            textarea-id="notes-agent-message"
            title="Агент за бележки"
            description="Тук работи само агентът за вашите лични бележки (notes). Историята на разговора се пази на сървъра; „Нов разговор“ започва изчистен контекст."
            placeholder="Например: Покажи ми бележките ми. / Създай бележка „Среща“ с описание …"
        />
    </AppLayout>
</template>
