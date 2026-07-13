<script setup lang="ts">
import { Loader2, Upload } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { store as documentsApiStore } from '@/routes/internal/documents';

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    uploaded: [documentId: number];
}>();

const { t } = useTranslations();

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const description = ref('');
const uploading = ref(false);
const errorMessage = ref<string | null>(null);

const resetForm = (): void => {
    selectedFile.value = null;
    description.value = '';
    errorMessage.value = null;

    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

watch(open, (isOpen) => {
    if (!isOpen) {
        resetForm();
    }
});

const getCsrfToken = (): string => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
};

const handleFileChange = (event: Event): void => {
    const input = event.target as HTMLInputElement;
    selectedFile.value = input.files?.[0] ?? null;
    errorMessage.value = null;
};

const upload = async (): Promise<void> => {
    if (selectedFile.value === null) {
        errorMessage.value = t('documents.upload_file_required');

        return;
    }

    uploading.value = true;
    errorMessage.value = null;

    const formData = new FormData();
    formData.append('file', selectedFile.value);

    if (description.value.trim() !== '') {
        formData.append('description', description.value.trim());
    }

    try {
        const response = await fetch(documentsApiStore().url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: formData,
        });

        if (!response.ok) {
            if (response.status === 422) {
                const payload = (await response.json()) as {
                    errors?: Record<string, string[]>;
                    message?: string;
                };
                const errors = payload.errors
                    ? Object.values(payload.errors).flat().join('\n')
                    : payload.message;

                errorMessage.value =
                    errors ?? t('documents.upload_error');

                return;
            }

            errorMessage.value = t('documents.upload_error');

            return;
        }

        const payload = (await response.json()) as { data: { id: number } };
        emit('uploaded', payload.data.id);
        open.value = false;
    } catch {
        errorMessage.value = t('documents.upload_error');
    } finally {
        uploading.value = false;
    }
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ t('documents.upload_title') }}</DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="upload">
                <div class="grid gap-2">
                    <Label for="task-document-file">{{
                        t('documents.fields.file')
                    }}</Label>
                    <Input
                        id="task-document-file"
                        ref="fileInput"
                        type="file"
                        required
                        @change="handleFileChange"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="task-document-description">{{
                        t('documents.fields.description')
                    }}</Label>
                    <textarea
                        id="task-document-description"
                        v-model="description"
                        rows="3"
                        class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    />
                </div>

                <InputError :message="errorMessage ?? undefined" />

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="uploading"
                        @click="open = false"
                    >
                        {{ t('common.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="uploading">
                        <Loader2
                            v-if="uploading"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        <Upload v-else class="mr-2 h-4 w-4" />
                        {{ t('documents.upload') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
