<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AgentChatPanel from '@/components/AgentChatPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import dashboardRoutes, {
    agent as postOrchestratorMessage,
} from '@/routes/dashboard';
import type { BreadcrumbItem } from '@/types';

const page = usePage();

const isAdmin = computed(() => page.props.auth.user?.is_admin === true);

const messagesUrl = (id: string) =>
    dashboardRoutes.agent.conversation.messages.url(id);
const feedbackUrl = (id: string) =>
    dashboardRoutes.agent.message.feedback.url(id);
const emailUrl = (id: string) => dashboardRoutes.agent.message.email.url(id);
const pdfUrl = (id: string) => dashboardRoutes.agent.message.pdf.url(id);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Табло',
        href: dashboard(),
    },
];

const pageDescriptionUser =
    'Общ агент: разбира заявката ви и ползва наличните инструменти (напр. бележки). Разговорът се пази на сървъра — за нов контекст ползвайте „Нов разговор“. За фокус само върху бележки отворете „Бележки“ от менюто.';

const pageDescriptionAdmin =
    'Табло за администратори — тук по-късно ще се добави съдържание.';
</script>

<template>
    <Head title="Табло" />

    <AppLayout
        :breadcrumbs="breadcrumbs"
        :page-title="isAdmin ? 'Табло' : 'Офис координатор'"
        :page-description="isAdmin ? pageDescriptionAdmin : pageDescriptionUser"
    >
        <div
            v-if="isAdmin"
            class="flex min-h-0 flex-1 flex-col items-center justify-center overflow-auto p-6 md:p-10"
        >
            <div
                class="w-full max-w-xl rounded-xl border border-border bg-card p-10 text-center shadow-sm"
            >
                <p class="text-sm text-muted-foreground">
                    Това табло е запазено за бъдещо съдържание. Ползвайте менюто
                    за управление на потребители и статистика.
                </p>
            </div>
        </div>
        <div v-else class="flex min-h-0 flex-1 flex-col overflow-hidden">
            <AgentChatPanel
                :post-url="postOrchestratorMessage.url()"
                :messages-url="messagesUrl"
                :conversations-url="dashboardRoutes.agent.conversations.url()"
                :delete-all-conversations-url="
                    dashboardRoutes.agent.conversations.destroy.url()
                "
                :feedback-url="feedbackUrl"
                :email-url="emailUrl"
                :pdf-url="pdfUrl"
                session-key="office-orchestrator"
                placeholder="Вашата заявка, например: Обобщи какво мога да правя тук. / Покажи бележките ми. / Как да създам бележка?"
            />
        </div>
    </AppLayout>
</template>
