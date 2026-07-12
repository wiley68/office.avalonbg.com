<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAppToast } from '@/composables/useAppToast';
import { useTranslations } from '@/composables/useTranslations';
import type { AccessListItem } from '@/pages/accesses/columns';
import { store, update } from '@/routes/accesses';

type Props = {
    open: boolean;
    mode: 'create' | 'edit';
    access?: AccessListItem | null;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [];
}>();

const { t } = useTranslations();
const { showError, showMessage } = useAppToast();
const page = usePage();

const isTransforming = ref(false);

const form = useForm({
    name: '',
    category: '',
    content: '',
    is_encrypted: false,
});

const dialogTitle = computed(() =>
    props.mode === 'create'
        ? t('accesses.create_title')
        : t('accesses.edit_title'),
);

const hasEncryptionKey = computed(
    () => page.props.auth.user?.has_access_encryption_key === true,
);

const resetForm = (): void => {
    form.reset();
    form.clearErrors();
};

watch(
    () => [props.open, props.mode, props.access] as const,
    ([open, mode, access]) => {
        if (!open) {
            return;
        }

        resetForm();

        if (mode === 'edit' && access) {
            form.name = access.name;
            form.category = access.category ?? '';
            form.content = access.content;
            form.is_encrypted = access.is_encrypted;
        }
    },
);

const close = (): void => {
    emit('update:open', false);
};

const transformData = async (action: 'encrypt' | 'decrypt'): Promise<void> => {
    if (!hasEncryptionKey.value) {
        showError(t('common.error'), t('accesses.errors.no_encryption_key'));

        return;
    }

    if (form.content.trim() === '') {
        return;
    }

    isTransforming.value = true;

    try {
        const response = await fetch('/internal-api/accesses/transform-data', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
                        ?.content ?? '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                action,
                content: form.content,
                is_encrypted: form.is_encrypted,
            }),
        });

        const payload = (await response.json()) as {
            content?: string;
            is_encrypted?: boolean;
            message?: string;
        };

        if (!response.ok) {
            showError(t('common.error'), payload.message ?? t('common.error'));

            return;
        }

        form.content = payload.content ?? form.content;
        form.is_encrypted = payload.is_encrypted ?? form.is_encrypted;
    } catch {
        showError(t('common.error'), t('common.error'));
    } finally {
        isTransforming.value = false;
    }
};

const submit = (): void => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showMessage(
                t('common.success'),
                props.mode === 'create'
                    ? t('accesses.create_title')
                    : t('accesses.edit_title'),
            );
            close();
            emit('saved');
        },
        onError: (errors: Record<string, string>) => {
            showError(
                t('common.error'),
                Object.values(errors).flat().join('\n'),
            );
        },
    };

    if (props.mode === 'create') {
        form.post(store().url, options);

        return;
    }

    if (props.access) {
        form.put(update(props.access.id).url, options);
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[90vh] max-w-lg overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                <DialogDescription>
                    {{ t('accesses.fields.data_placeholder') }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="access-name">{{ t('accesses.fields.name') }}</Label>
                    <Input
                        id="access-name"
                        v-model="form.name"
                        :placeholder="t('accesses.fields.name_placeholder')"
                        maxlength="40"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="access-category">{{
                        t('accesses.fields.category')
                    }}</Label>
                    <Input
                        id="access-category"
                        v-model="form.category"
                        :placeholder="t('accesses.fields.category_placeholder')"
                        maxlength="40"
                    />
                    <InputError :message="form.errors.category" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between gap-2">
                        <Label for="access-data">{{ t('accesses.fields.data') }}</Label>
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="isTransforming || form.content.trim() === ''"
                                @click="transformData('encrypt')"
                            >
                                <Loader2
                                    v-if="isTransforming"
                                    class="mr-1 h-3 w-3 animate-spin"
                                />
                                {{
                                    isTransforming
                                        ? t('accesses.actions.encrypting')
                                        : t('accesses.actions.encrypt')
                                }}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="
                                    isTransforming ||
                                    form.content.trim() === '' ||
                                    !form.is_encrypted
                                "
                                @click="transformData('decrypt')"
                            >
                                {{
                                    isTransforming
                                        ? t('accesses.actions.decrypting')
                                        : t('accesses.actions.decrypt')
                                }}
                            </Button>
                        </div>
                    </div>
                    <textarea
                        id="access-data"
                        v-model="form.content"
                        rows="8"
                        required
                        class="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 font-mono text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                        :placeholder="t('accesses.fields.data_placeholder')"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{
                            form.is_encrypted
                                ? t('accesses.fields.encrypted')
                                : t('accesses.fields.plain')
                        }}
                    </p>
                    <InputError :message="form.errors.content" />
                </div>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button type="button" variant="outline" @click="close">
                        {{ t('common.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        <Loader2
                            v-if="form.processing"
                            class="mr-2 h-4 w-4 animate-spin"
                        />
                        {{ t('common.save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
