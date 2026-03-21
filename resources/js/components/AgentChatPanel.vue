<script setup lang="ts">
import { computed, ref } from 'vue';

type Props = {
    /** Wayfinder URL string за POST към агента */
    postUrl: string;
    title: string;
    description: string;
    placeholder?: string;
    textareaId?: string;
    submitLabel?: string;
};

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Напишете заявката си…',
    textareaId: 'agent-message',
    submitLabel: 'Изпрати към агента',
});

const message = ref('');
const reply = ref<string | null>(null);
const error = ref<string | null>(null);
const validationErrors = ref<string[]>([]);
const sending = ref(false);

const canSend = computed(
    () => message.value.trim().length > 0 && !sending.value,
);

const csrfToken = (): string =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

const submit = async (): Promise<void> => {
    if (!canSend.value) {
        return;
    }

    reply.value = null;
    error.value = null;
    validationErrors.value = [];
    sending.value = true;

    try {
        const response = await fetch(props.postUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ message: message.value }),
        });

        const data = (await response.json()) as {
            reply?: string;
            message?: string;
            error?: string | null;
            errors?: Record<string, string[]>;
        };

        if (response.status === 422 && data.errors) {
            validationErrors.value = Object.values(data.errors).flat();

            return;
        }

        if (!response.ok) {
            error.value =
                data.message ??
                data.error ??
                `Грешка ${response.status}. Опитайте отново.`;

            return;
        }

        if (typeof data.reply === 'string') {
            reply.value = data.reply;
        }
    } catch (e) {
        error.value =
            e instanceof Error ? e.message : 'Неуспешна заявка към сървъра.';
    } finally {
        sending.value = false;
    }
};
</script>

<template>
    <div class="mx-auto flex max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
        <div>
            <h1
                class="text-2xl font-semibold tracking-tight text-foreground"
            >
                {{ title }}
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>

        <div class="flex flex-col gap-3">
            <label
                :for="textareaId"
                class="text-sm font-medium text-foreground"
            >
                Вашата заявка
            </label>
            <textarea
                :id="textareaId"
                v-model="message"
                rows="6"
                class="min-h-[140px] w-full resize-y rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                :placeholder="placeholder"
                :disabled="sending"
                @keydown.ctrl.enter.prevent="submit"
                @keydown.meta.enter.prevent="submit"
            />
            <p class="text-xs text-muted-foreground">
                Ctrl+Enter или ⌘+Enter за изпращане.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
                :disabled="!canSend"
                @click="submit"
            >
                <span
                    v-if="sending"
                    class="mr-2 size-4 animate-spin rounded-full border-2 border-primary-foreground/30 border-t-primary-foreground"
                />
                {{ sending ? 'Изпращане…' : submitLabel }}
            </button>
        </div>

        <div
            v-if="validationErrors.length"
            class="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
            <ul class="list-inside list-disc space-y-1">
                <li v-for="(err, i) in validationErrors" :key="i">
                    {{ err }}
                </li>
            </ul>
        </div>

        <div
            v-if="error"
            class="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
            {{ error }}
        </div>

        <div v-if="reply !== null" class="flex flex-col gap-2">
            <h2 class="text-sm font-medium text-foreground">
                Отговор на агента
            </h2>
            <pre
                class="max-h-[min(480px,70vh)] overflow-auto whitespace-pre-wrap rounded-lg border border-sidebar-border/70 bg-muted/40 p-4 text-sm text-foreground dark:border-sidebar-border"
                >{{ reply }}</pre
            >
        </div>
    </div>
</template>
