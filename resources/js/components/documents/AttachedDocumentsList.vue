<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Download, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { download as downloadDocumentRoute } from '@/routes/documents';

type DocumentItem = {
    id: number;
    original_name: string;
    description?: string | null;
};

type Props = {
    documents: DocumentItem[];
    detachUrlBuilder: (documentId: number) => string;
    downloadUrlBuilder?: (documentId: number) => string;
};

const props = withDefaults(defineProps<Props>(), {
    downloadUrlBuilder: (documentId: number) =>
        downloadDocumentRoute(documentId, { query: { download: 1 } }).url,
});

const emit = defineEmits<{
    detached: [];
}>();

const { t } = useTranslations();

const detach = (documentId: number): void => {
    router.delete(props.detachUrlBuilder(documentId), {
        preserveScroll: true,
        onSuccess: () => emit('detached'),
    });
};
</script>

<template>
    <div v-if="documents.length > 0" class="space-y-2">
        <p class="text-xs font-medium text-muted-foreground">
            {{ t('projects.documents.attached') }}
        </p>
        <div
            v-for="document in documents"
            :key="document.id"
            class="flex items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm"
        >
            <div>
                <p>{{ document.original_name }}</p>
                <p
                    v-if="document.description"
                    class="text-xs text-muted-foreground"
                >
                    {{ document.description }}
                </p>
            </div>
            <div class="flex gap-1">
                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    as="a"
                    :href="downloadUrlBuilder(document.id)"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <Download class="h-4 w-4" />
                </Button>
                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    @click="detach(document.id)"
                >
                    <X class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
