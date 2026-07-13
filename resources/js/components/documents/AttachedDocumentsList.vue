<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Download, X } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTranslations } from '@/composables/useTranslations';
import { documentTypeIconUrl } from '@/lib/documentTypeIcon';
import { openDocument } from '@/lib/openDocument';
import { formatDocumentMimeType } from '@/pages/documents/columns';
import { download as downloadDocumentRoute } from '@/routes/documents';

type DocumentItem = {
    id: number;
    original_name: string;
    mime_type: string;
    description?: string | null;
};

type Props = {
    documents: DocumentItem[];
    detachUrlBuilder: (documentId: number) => string;
    downloadUrlBuilder?: (documentId: number) => string;
    confirmBeforeDetach?: boolean;
    detachConfirmDescription?: string;
};

const props = withDefaults(defineProps<Props>(), {
    downloadUrlBuilder: (documentId: number) =>
        downloadDocumentRoute(documentId, { query: { download: 1 } }).url,
    confirmBeforeDetach: false,
    detachConfirmDescription: undefined,
});

const emit = defineEmits<{
    detached: [];
}>();

const { t } = useTranslations();
const page = usePage();

const showDetachDialog = ref(false);
const documentToDetach = ref<number | null>(null);

const detach = (documentId: number): void => {
    router.delete(props.detachUrlBuilder(documentId), {
        preserveScroll: true,
        onSuccess: () => emit('detached'),
    });
};

const requestDetach = (documentId: number): void => {
    if (props.confirmBeforeDetach) {
        documentToDetach.value = documentId;
        showDetachDialog.value = true;

        return;
    }

    detach(documentId);
};

const cancelDetach = (): void => {
    documentToDetach.value = null;
    showDetachDialog.value = false;
};

const confirmDetach = (): void => {
    if (documentToDetach.value === null) {
        return;
    }

    const documentId = documentToDetach.value;
    documentToDetach.value = null;
    showDetachDialog.value = false;
    detach(documentId);
};

const iconUrl = (mimeType: string): string =>
    documentTypeIconUrl(
        mimeType,
        page.props.documentIconsVersion as string,
    );

const resolvedDetachConfirmDescription = computed(
    () =>
        props.detachConfirmDescription ??
        t('projects.documents.detach_confirm'),
);
</script>

<template>
    <div v-if="documents.length > 0" class="space-y-2">
        <p class="text-xs font-medium text-muted-foreground">
            {{ t('projects.documents.attached') }}
        </p>

        <TooltipProvider :delay-duration="200">
            <div
                class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 2xl:grid-cols-12"
            >
                <div
                    v-for="document in documents"
                    :key="document.id"
                    class="space-y-2 rounded-md border p-2 transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/25 hover:bg-muted/30 hover:shadow-md"
                >
                    <div
                        class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-2"
                    >
                        <button
                            type="button"
                            class="cursor-pointer rounded-md transition-opacity hover:opacity-80 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :aria-label="t('documents.actions.open')"
                            @click="openDocument(document.id)"
                        >
                            <img
                                :src="iconUrl(document.mime_type)"
                                :alt="formatDocumentMimeType(document.mime_type)"
                                class="pointer-events-none size-16 shrink-0 object-contain"
                                loading="lazy"
                            />
                        </button>

                        <div class="flex shrink-0 flex-col gap-0.5">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        class="size-8"
                                        as="a"
                                        :href="downloadUrlBuilder(document.id)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        :aria-label="
                                            t('documents.actions.download')
                                        "
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
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        class="size-8 text-destructive hover:text-destructive"
                                        :aria-label="
                                            t('projects.documents.detach')
                                        "
                                        @click="requestDetach(document.id)"
                                    >
                                        <X class="size-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent side="left">
                                    {{ t('projects.documents.detach') }}
                                </TooltipContent>
                            </Tooltip>
                        </div>
                    </div>

                    <p
                        class="truncate text-sm font-medium"
                        :title="document.original_name"
                    >
                        {{ document.original_name }}
                    </p>
                </div>
            </div>
        </TooltipProvider>

        <AppAlertDialog
            v-model:open="showDetachDialog"
            :title="t('users.delete_confirm_title')"
            :description="resolvedDetachConfirmDescription"
            @confirm="confirmDetach"
            @cancel="cancelDetach"
        />
    </div>
</template>
