<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { useAppToastStore } from '@/composables/useAppToast';
import { cn } from '@/lib/utils';

const { toasts, removeToast, pauseToast, resumeToast } = useAppToastStore();
</script>

<template>
    <div
        class="pointer-events-none fixed right-4 bottom-4 z-50 flex w-full max-w-sm flex-col gap-2"
        aria-live="polite"
    >
        <div
            v-for="toast in toasts"
            :key="toast.id"
            :class="
                cn(
                    'pointer-events-auto rounded-lg border p-4 shadow-lg',
                    toast.type === 'success'
                        ? 'border-border bg-background text-foreground'
                        : 'border-destructive bg-background text-foreground',
                )
            "
            @mouseenter="pauseToast(toast.id)"
            @mouseleave="resumeToast(toast.id)"
        >
            <div class="flex items-start gap-3">
                <div class="min-w-0 flex-1">
                    <p
                        class="text-sm font-medium"
                        :class="toast.type === 'error' ? 'text-destructive' : undefined"
                    >
                        {{ toast.title }}
                    </p>
                    <p
                        v-if="toast.message"
                        class="mt-1 text-sm text-foreground"
                    >
                        {{ toast.message }}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-6 shrink-0"
                    @click="removeToast(toast.id)"
                >
                    <X class="size-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
