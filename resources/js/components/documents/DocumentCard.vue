<script setup lang="ts">
import { Download, FileUp, Pencil, Trash2 } from 'lucide-vue-next';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTranslations } from '@/composables/useTranslations';
import { documentTypeIconUrl } from '@/lib/documentTypeIcon';
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
    view: [documentId: number];
    download: [documentId: number];
    replace: [document: DocumentListItem];
}>();

const { t } = useTranslations();
const page = usePage();

const description = computed(
    () =>
        props.document.description?.trim() || t('documents.no_description'),
);

const typeIconUrl = computed(() =>
    documentTypeIconUrl(
        props.document.mime_type,
        page.props.documentIconsVersion as string,
    ),
);

const typeLabel = computed(() =>
    formatDocumentMimeType(props.document.mime_type),
);
</script>

<template>
    <Card
        class="gap-0 py-4 transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/25 hover:bg-muted/30 hover:shadow-lg"
    >
        <CardContent class="space-y-4 px-6">
            <TooltipProvider :delay-duration="200">
                <div
                    class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-4"
                >
                    <div class="flex min-w-0 items-center justify-start">
                        <button
                            type="button"
                            class="cursor-pointer rounded-md transition-opacity hover:opacity-80 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :aria-label="t('documents.actions.open')"
                            @click="emit('view', document.id)"
                        >
                            <img
                                :src="typeIconUrl"
                                :alt="typeLabel"
                                class="pointer-events-none size-32 shrink-0 object-contain"
                                loading="lazy"
                            />
                        </button>
                    </div>

                    <div class="flex shrink-0 flex-col items-center gap-1">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 shrink-0 cursor-pointer text-primary hover:text-primary"
                                    :aria-label="t('documents.actions.download')"
                                    @click="emit('download', document.id)"
                                >
                                    <Download class="size-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="left">
                                {{ t('documents.actions.download') }}
                            </TooltipContent>
                        </Tooltip>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 shrink-0 cursor-pointer"
                                    :aria-label="t('documents.actions.replace')"
                                    @click="emit('replace', document)"
                                >
                                    <FileUp class="size-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="left">
                                {{ t('documents.actions.replace') }}
                            </TooltipContent>
                        </Tooltip>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 shrink-0 cursor-pointer"
                                    :aria-label="t('common.edit')"
                                    @click="emit('edit', document)"
                                >
                                    <Pencil class="size-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="left">
                                {{ t('common.edit') }}
                            </TooltipContent>
                        </Tooltip>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 shrink-0 cursor-pointer text-destructive hover:text-destructive"
                                    :aria-label="t('common.delete')"
                                    @click="emit('delete', document.id)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="left">
                                {{ t('common.delete') }}
                            </TooltipContent>
                        </Tooltip>
                    </div>
                </div>
            </TooltipProvider>

            <div class="space-y-1 border-b pb-4">
                <p
                    class="text-base leading-snug font-semibold break-all"
                    :title="document.original_name"
                >
                    {{ document.original_name }}
                </p>
                <p class="line-clamp-3 text-sm text-muted-foreground">
                    {{ description }}
                </p>
            </div>

            <dl class="grid grid-cols-2 gap-4 text-sm">
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
            </dl>
        </CardContent>
    </Card>
</template>
