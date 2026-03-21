<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AgentChatPanel from '@/components/AgentChatPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import dashboardRoutes, {
    agent as postOrchestratorMessage,
} from '@/routes/dashboard';
import type { BreadcrumbItem } from '@/types';

const messagesUrl = (id: string) =>
    dashboardRoutes.agent.conversation.messages.url(id);
const feedbackUrl = (id: string) =>
    dashboardRoutes.agent.message.feedback.url(id);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Табло',
        href: dashboard(),
    },
];

const pageDescription =
    'Общ агент: разбира заявката ви и ползва наличните инструменти (напр. бележки). Разговорът се пази на сървъра — за нов контекст ползвайте „Нов разговор“. За фокус само върху бележки отворете „Бележки“ от менюто.';
</script>

<template>
    <Head title="Табло" />

    <AppLayout
        :breadcrumbs="breadcrumbs"
        page-title="Офис координатор"
        :page-description="pageDescription"
    >
        <AgentChatPanel
            :post-url="postOrchestratorMessage.url()"
            :messages-url="messagesUrl"
            :conversations-url="dashboardRoutes.agent.conversations.url()"
            :feedback-url="feedbackUrl"
            session-key="office-orchestrator"
            placeholder="Вашата заявка, например: Обобщи какво мога да правя тук. / Покажи бележките ми. / Как да създам бележка?"
        />
    </AppLayout>
</template>
