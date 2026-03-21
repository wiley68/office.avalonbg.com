<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AgentChatPanel from '@/components/AgentChatPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { agent as postOrchestratorMessage } from '@/routes/dashboard';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Табло',
        href: dashboard(),
    },
];
</script>

<template>
    <Head title="Табло" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <AgentChatPanel
            :post-url="postOrchestratorMessage.url()"
            session-key="office-orchestrator"
            title="Офис координатор"
            description="Общ агент: разбира заявката ви и ползва наличните инструменти (напр. бележки). Разговорът се пази на сървъра — за нов контекст ползвайте „Нов разговор“. За фокус само върху бележки отворете „Бележки“ от менюто."
            placeholder="Например: Обобщи какво мога да правя тук. / Покажи бележките ми. / Как да създам бележка?"
        />
    </AppLayout>
</template>
