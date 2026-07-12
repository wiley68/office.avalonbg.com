<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { documentsApiIndex } from '@/composables/useDocumentsApiRoute';
import { useTranslations } from '@/composables/useTranslations';

type DocumentListItem = {
    id: number;
    original_name: string;
    description: string | null;
    created_at: string;
};

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    select: [documentId: number];
}>();

const { t } = useTranslations();

const documents = ref<DocumentListItem[]>([]);
const loading = ref(false);
const search = ref('');

const filteredDocuments = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (query === '') {
        return documents.value;
    }

    return documents.value.filter(
        (document) =>
            document.original_name.toLowerCase().includes(query) ||
            (document.description ?? '').toLowerCase().includes(query),
    );
});

const fetchDocuments = async (): Promise<void> => {
    loading.value = true;

    try {
        const params = new URLSearchParams({
            page: '1',
            per_page: '100',
            sort_by: 'created_at',
            sort_desc: '1',
        });

        const response = await fetch(`${documentsApiIndex().url}?${params}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const payload = (await response.json()) as { data: DocumentListItem[] };
        documents.value = payload.data;
    } finally {
        loading.value = false;
    }
};

watch(open, (isOpen) => {
    if (isOpen) {
        fetchDocuments();
    }
});

onMounted(() => {
    if (open.value) {
        fetchDocuments();
    }
});

const selectDocument = (documentId: number): void => {
    emit('select', documentId);
    open.value = false;
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[80vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{ t('documents.picker.title') }}</DialogTitle>
            </DialogHeader>

            <Input
                v-model="search"
                :placeholder="t('documents.search_placeholder')"
            />

            <div
                v-if="loading"
                class="py-8 text-center text-sm text-muted-foreground"
            >
                {{ t('common.table.loading') }}
            </div>

            <div
                v-else-if="filteredDocuments.length === 0"
                class="py-8 text-center text-sm text-muted-foreground"
            >
                {{ t('documents.empty') }}
            </div>

            <div v-else class="space-y-2">
                <div
                    v-for="document in filteredDocuments"
                    :key="document.id"
                    class="flex items-center justify-between gap-4 rounded-md border p-3"
                >
                    <div>
                        <p class="font-medium">{{ document.original_name }}</p>
                        <p
                            v-if="document.description"
                            class="text-sm text-muted-foreground"
                        >
                            {{ document.description }}
                        </p>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        @click="selectDocument(document.id)"
                    >
                        {{ t('documents.picker.select') }}
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
