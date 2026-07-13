<script setup lang="ts">
import { Download, Pencil, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTranslations } from '@/composables/useTranslations';
import type { DocumentListItem } from '@/pages/documents/columns';
import {
    formatDocumentDate,
    formatDocumentMimeType,
    formatDocumentSize,
} from '@/pages/documents/columns';

const props = defineProps<{
    document: DocumentListItem;
}>();

const emit = defineEmits<{
    edit: [document: DocumentListItem];
    delete: [documentId: number];
    download: [documentId: number];
}>();

const { t } = useTranslations();

const description = computed(
    () =>
        props.document.description?.trim() || t('documents.no_description'),
);
</script>

<template>
    <Card class="gap-4 py-4">
        <TooltipProvider :delay-duration="200">
            <CardHeader
                class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-4 gap-y-2 border-b pb-4 [.border-b]:pb-4"
            >
                <CardTitle
                    class="min-w-0 text-base leading-snug font-semibold"
                    :title="document.original_name"
                >
                    <span class="line-clamp-2 break-all">
                        {{ document.original_name }}
                    </span>
                </CardTitle>

                <div class="flex shrink-0 items-center justify-end gap-1">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8 shrink-0"
                                :aria-label="t('common.edit')"
                                @click="emit('edit', document)"
                            >
                                <Pencil class="size-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="top">
                            {{ t('common.edit') }}
                        </TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8 shrink-0 text-destructive hover:text-destructive"
                                :aria-label="t('common.delete')"
                                @click="emit('delete', document.id)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="top">
                            {{ t('common.delete') }}
                        </TooltipContent>
                    </Tooltip>
                </div>

                <CardDescription class="min-w-0 line-clamp-3 text-sm">
                    {{ description }}
                </CardDescription>

                <div class="flex shrink-0 items-center justify-end">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8 shrink-0 text-primary hover:text-primary"
                                :aria-label="t('documents.actions.download')"
                                @click="emit('download', document.id)"
                            >
                                <Download class="size-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="top">
                            {{ t('documents.actions.download') }}
                        </TooltipContent>
                    </Tooltip>
                </div>
            </CardHeader>
        </TooltipProvider>

        <CardContent class="px-6 pt-0">
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-muted-foreground">
                        {{ t('documents.columns.size') }}
                    </dt>
                    <dd class="font-medium">
                        {{ formatDocumentSize(document.size_bytes) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ t('documents.columns.created_at') }}
                    </dt>
                    <dd class="font-medium">
                        {{ formatDocumentDate(document.created_at) }}
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-muted-foreground">
                        {{ t('documents.fields.type') }}
                    </dt>
                    <dd class="font-medium">
                        {{ formatDocumentMimeType(document.mime_type) }}
                    </dd>
                </div>
            </dl>
        </CardContent>
    </Card>
</template>
