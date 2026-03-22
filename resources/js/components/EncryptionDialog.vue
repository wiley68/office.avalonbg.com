<script setup lang="ts">
import { Lock } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import dashboardRoutes from '@/routes/dashboard';

type Props = {
    placement: 'sidebar' | 'header-desktop' | 'header-mobile';
};

defineProps<Props>();

const open = ref(false);
const text = ref('');
const error = ref<string | null>(null);
const feedback = ref<string | null>(null);
const busy = ref(false);

const csrfToken = (): string =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

watch(open, (isOpen) => {
    if (isOpen) {
        text.value = '';
        error.value = null;
        feedback.value = null;
        busy.value = false;
    }
});

const postCrypto = async (
    path: string,
): Promise<{ ok: boolean; text?: string; message?: string }> => {
    const response = await fetch(path, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ text: text.value }),
    });

    const contentType = response.headers.get('Content-Type') ?? '';

    if (response.status === 422 && contentType.includes('application/json')) {
        const data = (await response.json()) as {
            message?: string;
            errors?: Record<string, string[]>;
        };

        if (data.errors?.text?.[0]) {
            return { ok: false, message: data.errors.text[0] };
        }

        if (data.message) {
            return { ok: false, message: data.message };
        }
    }

    if (!response.ok || !contentType.includes('application/json')) {
        return {
            ok: false,
            message: `Грешка ${response.status}. Опитайте отново.`,
        };
    }

    const data = (await response.json()) as { text?: string };

    if (typeof data.text !== 'string') {
        return { ok: false, message: 'Неочакван отговор от сървъра.' };
    }

    return { ok: true, text: data.text };
};

const encrypt = async (): Promise<void> => {
    busy.value = true;
    error.value = null;
    feedback.value = null;

    try {
        const result = await postCrypto(dashboardRoutes.crypto.encrypt.url());

        if (!result.ok) {
            error.value = result.message ?? 'Неуспешно криптиране.';

            return;
        }

        text.value = result.text ?? '';
        feedback.value = 'Текстът е криптиран в полето.';
    } catch {
        error.value = 'Мрежова грешка.';
    } finally {
        busy.value = false;
    }
};

const decrypt = async (): Promise<void> => {
    busy.value = true;
    error.value = null;
    feedback.value = null;

    try {
        const result = await postCrypto(dashboardRoutes.crypto.decrypt.url());

        if (!result.ok) {
            error.value = result.message ?? 'Неуспешно декриптиране.';

            return;
        }

        text.value = result.text ?? '';
        feedback.value = 'Текстът е декриптиран в полето.';
    } catch {
        error.value = 'Мрежова грешка.';
    } finally {
        busy.value = false;
    }
};

const copyText = async (): Promise<void> => {
    error.value = null;
    feedback.value = null;

    try {
        await navigator.clipboard.writeText(text.value);
        open.value = false;
    } catch {
        error.value = 'Неуспешно копиране. Проверете разрешенията на браузъра.';
    }
};

const textareaClass = cn(
    'min-h-[140px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground',
    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
    'outline-none disabled:cursor-not-allowed disabled:opacity-50',
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <template v-if="placement === 'sidebar'">
                <SidebarGroup class="group-data-[collapsible=icon]:p-0">
                    <SidebarGroupContent>
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    type="button"
                                    class="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100"
                                >
                                    <Lock />
                                    <span>Криптиране</span>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </template>

            <template v-else-if="placement === 'header-mobile'">
                <button
                    type="button"
                    class="flex w-full items-center space-x-2 rounded-lg px-3 py-2 text-left text-sm font-medium hover:bg-accent"
                >
                    <Lock class="h-5 w-5" />
                    <span>Криптиране</span>
                </button>
            </template>

            <template v-else>
                <TooltipProvider :delay-duration="0">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                type="button"
                                class="group h-9 w-9 cursor-pointer"
                            >
                                <span class="sr-only">Криптиране</span>
                                <Lock
                                    class="size-5 opacity-80 group-hover:opacity-100"
                                />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>Криптиране</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </template>
        </DialogTrigger>

        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Криптиране</DialogTitle>
                <DialogDescription>
                    Копирайте обикновен или криптиран текст в полето. Ползвайте
                    бутоните за криптиране, декриптиране или копиране.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-2">
                <Label for="crypto-text-input">Текст</Label>
                <textarea
                    id="crypto-text-input"
                    v-model="text"
                    :class="textareaClass"
                    rows="8"
                    aria-label="Текст за криптиране или декриптиране"
                    autocomplete="off"
                />
                <p v-if="error" class="text-sm text-destructive">
                    {{ error }}
                </p>
                <p v-else-if="feedback" class="text-sm text-muted-foreground">
                    {{ feedback }}
                </p>
            </div>

            <DialogFooter class="flex-col gap-3 sm:flex-col">
                <div
                    class="flex w-full flex-wrap items-center justify-between gap-2"
                >
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            :disabled="busy"
                            @click="encrypt"
                        >
                            Криптирай
                        </Button>
                        <Button
                            type="button"
                            variant="secondary"
                            :disabled="busy"
                            @click="decrypt"
                        >
                            Декриптирай
                        </Button>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="busy || text.length === 0"
                        @click="copyText"
                    >
                        Копирай
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
