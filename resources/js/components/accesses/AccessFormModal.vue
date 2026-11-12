<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
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
import { useAccessRevocation } from '@/lib/accessRevocation';
import type { AccessListItem } from '@/pages/accesses/columns';
import { store, update } from '@/routes/accesses';

type Props = {
    open: boolean;
    encryptionContext: string;
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

const isTransforming = ref(false);

const emptyFormState = {
    name: '',
    category: '',
    content: '',
    is_encrypted: false,
    remove_encryption: false,
    encryption_context: props.encryptionContext,
    version: null as string | null,
};

const form = useForm({ ...emptyFormState });

const dialogTitle = computed(() =>
    props.mode === 'create'
        ? t('accesses.create_title')
        : t('accesses.edit_title'),
);

let formEpoch = 0;
let transformController: AbortController | null = null;

const invalidateTransform = (): void => {
    formEpoch++;
    transformController?.abort();
    transformController = null;
    isTransforming.value = false;
};

const clearForm = (): void => {
    invalidateTransform();
    form.defaults({
        ...emptyFormState,
        encryption_context: props.encryptionContext,
    });
    form.reset();
    form.clearErrors();
};

const requiresRemovalConfirmation = computed(
    () =>
        props.mode === 'edit' &&
        props.access?.is_encrypted &&
        !form.is_encrypted,
);
const busy = computed(() => isTransforming.value || form.processing);
const canEncrypt = computed(
    () =>
        props.open &&
        !busy.value &&
        !form.is_encrypted &&
        form.content.trim() !== '',
);
const canDecrypt = computed(
    () =>
        props.open &&
        !busy.value &&
        form.is_encrypted &&
        form.content.trim() !== '',
);
const canSubmit = computed(
    () =>
        props.open &&
        !busy.value &&
        (!requiresRemovalConfirmation.value || form.remove_encryption),
);

useAccessRevocation(() => {
    form.cancel();
    clearForm();
    emit('update:open', false);
});

watch(
    () =>
        [
            props.open,
            props.mode,
            props.access,
            props.encryptionContext,
        ] as const,
    ([open, mode, access]) => {
        clearForm();

        if (!open) {
            return;
        }

        if (mode === 'edit' && access) {
            form.name = access.name;
            form.category = access.category ?? '';
            form.content = access.content;
            form.is_encrypted = access.is_encrypted;
            form.encryption_context = access.encryption_context;
            form.version = access.version;
        }
    },
    { deep: true, immediate: true, flush: 'sync' },
);

watch(
    () => [
        form.content,
        form.is_encrypted,
        form.encryption_context,
        form.version,
    ],
    () => {
        if (transformController !== null) {
            invalidateTransform();
        }

        form.remove_encryption = false;
    },
    { flush: 'sync' },
);

const updateOpen = (open: boolean): void => {
    if (!open) {
        clearForm();
    }

    emit('update:open', open);
};

const close = (): void => updateOpen(false);

const transformData = async (action: 'encrypt' | 'decrypt'): Promise<void> => {
    if (action === 'encrypt' ? !canEncrypt.value : !canDecrypt.value) {
        return;
    }

    const epoch = formEpoch;
    const controller = new AbortController();
    transformController = controller;
    const context = form.encryption_context;
    isTransforming.value = true;

    try {
        const response = await fetch('/internal-api/accesses/transform-data', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    (
                        document.querySelector(
                            'meta[name="csrf-token"]',
                        ) as HTMLMetaElement
                    )?.content ?? '',
            },
            credentials: 'same-origin',
            signal: controller.signal,
            body: JSON.stringify({
                access_id: props.mode === 'edit' ? props.access?.id : null,
                encryption_context: form.encryption_context,
                version: form.version,
                action,
                content: form.content,
                is_encrypted: form.is_encrypted,
            }),
        });

        const payload = (await response.json()) as {
            content?: string;
            is_encrypted?: boolean;
            encryption_context?: string;
            message?: string;
        };

        if (
            epoch !== formEpoch ||
            transformController !== controller ||
            !props.open
        ) {
            return;
        }

        if (!response.ok) {
            showError(t('common.error'), payload.message ?? t('common.error'));

            return;
        }

        if (
            typeof payload.content !== 'string' ||
            payload.is_encrypted !== (action === 'encrypt') ||
            payload.encryption_context !== context
        ) {
            showError(t('common.error'), t('accesses.errors.operation_failed'));

            return;
        }

        transformController = null;
        form.content = payload.content;
        form.is_encrypted = payload.is_encrypted;
        isTransforming.value = false;
    } catch {
        if (epoch === formEpoch && transformController === controller) {
            showError(t('common.error'), t('common.error'));
        }
    } finally {
        if (transformController === controller) {
            transformController = null;
            isTransforming.value = false;
        }
    }
};

const submit = (): void => {
    if (!canSubmit.value) {
        return;
    }

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
    <Dialog :open="open" @update:open="updateOpen">
        <DialogContent class="max-h-[90vh] max-w-lg overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                <DialogDescription>
                    {{ t('accesses.fields.data_placeholder') }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="access-name">{{
                        t('accesses.fields.name')
                    }}</Label>
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
                        <Label for="access-data">{{
                            t('accesses.fields.data')
                        }}</Label>
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="!canEncrypt"
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
                                :disabled="!canDecrypt"
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
                        :disabled="busy"
                        :readonly="form.is_encrypted"
                        rows="8"
                        required
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
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
                    <InputError :message="form.errors.encryption_context" />
                    <InputError :message="form.errors.version" />
                    <label
                        v-if="requiresRemovalConfirmation"
                        for="access-remove-encryption"
                    >
                        <input
                            id="access-remove-encryption"
                            v-model="form.remove_encryption"
                            type="checkbox"
                            :disabled="busy"
                        />
                        {{ t('accesses.fields.remove_encryption') }}
                    </label>
                    <InputError :message="form.errors.remove_encryption" />
                </div>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button type="button" variant="outline" @click="close">
                        {{ t('common.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="!canSubmit">
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
