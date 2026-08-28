<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAppToast } from '@/composables/useAppToast';
import { profitTypesApiIndex } from '@/composables/useProfitTypesApiRoute';
import { useTranslations } from '@/composables/useTranslations';
import { store as storeProfitType } from '@/routes/profit-types';
import { store, update } from '@/routes/profits';
import type {
    ProfitEntryItem,
    ProfitTypeKind,
    ProfitTypeOption,
} from '@/types/profits';

type Props = {
    open: boolean;
    mode: 'create' | 'edit';
    entry?: ProfitEntryItem | null;
    defaultKind?: ProfitTypeKind;
};

const props = withDefaults(defineProps<Props>(), {
    entry: null,
    defaultKind: 'income',
});

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [];
}>();

const { t } = useTranslations();
const { showError, showMessage } = useAppToast();

const types = ref<ProfitTypeOption[]>([]);
const loadingTypes = ref(false);
const showNewType = ref(false);
const newTypeName = ref('');
const creatingType = ref(false);
const kind = ref<ProfitTypeKind>('income');

const emptyFormState = {
    profit_type_id: '',
    date: '',
    document_number: '',
    description: '',
    amount: '',
};

const form = useForm({ ...emptyFormState });

const dialogTitle = computed(() =>
    props.mode === 'create'
        ? t('profits.create_title')
        : t('profits.edit_title'),
);

const filteredTypes = computed(() =>
    types.value.filter((type) => type.kind === kind.value),
);

const clearForm = (): void => {
    form.defaults({ ...emptyFormState });
    form.reset();
    form.clearErrors();
    showNewType.value = false;
    newTypeName.value = '';
};

const selectFirstTypeForKind = (): void => {
    const first = filteredTypes.value[0];
    form.profit_type_id = first ? String(first.id) : '';
};

const fetchTypes = async (): Promise<void> => {
    loadingTypes.value = true;

    try {
        const response = await fetch(profitTypesApiIndex().url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(t('profits.errors.load_types'));
        }

        const payload = (await response.json()) as {
            data: ProfitTypeOption[];
        };

        types.value = payload.data;
    } catch (error) {
        showError(
            t('common.error'),
            error instanceof Error
                ? error.message
                : t('profits.errors.load_types'),
        );
    } finally {
        loadingTypes.value = false;
    }
};

watch(
    () => [props.open, props.mode, props.entry, props.defaultKind] as const,
    async ([open, mode, entry, defaultKind]) => {
        if (!open) {
            return;
        }

        clearForm();
        await fetchTypes();

        if (mode === 'edit' && entry) {
            kind.value = entry.type.kind;
            form.profit_type_id = String(entry.profit_type_id);
            form.date = entry.date;
            form.document_number = entry.document_number ?? '';
            form.description = entry.description ?? '';
            form.amount = entry.amount;
        } else {
            kind.value = defaultKind;
            form.date = new Date().toISOString().slice(0, 10);
            selectFirstTypeForKind();
        }
    },
);

watch(
    () => props.open,
    (open) => {
        if (!open) {
            clearForm();
        }
    },
);

watch(kind, () => {
    if (!props.open) {
        return;
    }

    const stillValid = filteredTypes.value.some(
        (type) => String(type.id) === form.profit_type_id,
    );

    if (!stillValid) {
        selectFirstTypeForKind();
    }
});

const close = (): void => {
    emit('update:open', false);
};

const createType = (): void => {
    const name = newTypeName.value.trim();

    if (name === '') {
        return;
    }

    creatingType.value = true;

    router.post(
        storeProfitType().url,
        {
            name,
            kind: kind.value,
        },
        {
            preserveScroll: true,
            onSuccess: async () => {
                showMessage(t('common.success'), t('profits.type_created'));
                newTypeName.value = '';
                showNewType.value = false;
                await fetchTypes();

                const created = types.value.find(
                    (type) => type.name === name && type.kind === kind.value,
                );

                if (created) {
                    form.profit_type_id = String(created.id);
                }
            },
            onError: () => {
                showError(t('common.error'), t('profits.errors.create_type'));
            },
            onFinish: () => {
                creatingType.value = false;
            },
        },
    );
};

const submit = (): void => {
    const payload = {
        profit_type_id: Number(form.profit_type_id),
        date: form.date,
        document_number: form.document_number || null,
        description: form.description.trim() === '' ? null : form.description,
        amount: form.amount,
    };

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
            close();
        },
    };

    if (props.mode === 'edit' && props.entry) {
        form.transform(() => payload).put(update(props.entry.id).url, options);
    } else {
        form.transform(() => payload).post(store().url, options);
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                <DialogDescription>
                    {{ t('profits.form_description') }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label>{{ t('profits.fields.kind') }}</Label>
                    <Select v-model="kind">
                        <SelectTrigger class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="income">
                                {{ t('profits.kinds.income') }}
                            </SelectItem>
                            <SelectItem value="expense">
                                {{ t('profits.kinds.expense') }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-2">
                    <Label for="profit-date">{{
                        t('profits.fields.date')
                    }}</Label>
                    <Input
                        id="profit-date"
                        v-model="form.date"
                        type="date"
                        required
                    />
                    <InputError :message="form.errors.date" />
                </div>

                <div class="grid gap-2">
                    <Label for="profit-document">{{
                        t('profits.fields.document_number')
                    }}</Label>
                    <Input
                        id="profit-document"
                        v-model="form.document_number"
                        maxlength="64"
                        :placeholder="
                            t('profits.fields.document_number_placeholder')
                        "
                    />
                    <InputError :message="form.errors.document_number" />
                </div>

                <div class="grid gap-2">
                    <Label for="profit-description">{{
                        t('profits.fields.description')
                    }}</Label>
                    <textarea
                        id="profit-description"
                        v-model="form.description"
                        rows="3"
                        :placeholder="t('profits.fields.description_placeholder')"
                        class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between gap-2">
                        <Label>{{ t('profits.fields.type') }}</Label>
                        <Button
                            type="button"
                            variant="link"
                            class="h-auto p-0 text-xs"
                            @click="showNewType = !showNewType"
                        >
                            {{
                                showNewType
                                    ? t('common.cancel')
                                    : t('profits.add_type')
                            }}
                        </Button>
                    </div>

                    <Select v-model="form.profit_type_id">
                        <SelectTrigger class="w-full">
                            <SelectValue
                                :placeholder="
                                    t('profits.fields.type_placeholder')
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="type in filteredTypes"
                                :key="type.id"
                                :value="String(type.id)"
                            >
                                {{ type.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.profit_type_id" />

                    <div
                        v-if="showNewType"
                        class="flex flex-col gap-2 rounded-md border p-3 sm:flex-row"
                    >
                        <Input
                            v-model="newTypeName"
                            :placeholder="
                                t('profits.fields.new_type_placeholder')
                            "
                            class="flex-1"
                        />
                        <Button
                            type="button"
                            :disabled="
                                creatingType || newTypeName.trim() === ''
                            "
                            @click="createType"
                        >
                            {{ t('profits.save_type') }}
                        </Button>
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="profit-amount">{{
                        t('profits.fields.amount')
                    }}</Label>
                    <Input
                        id="profit-amount"
                        v-model="form.amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        required
                    />
                    <InputError :message="form.errors.amount" />
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="close">
                        {{ t('common.cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing || loadingTypes"
                    >
                        {{
                            form.processing
                                ? t('common.saving')
                                : t('common.save')
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
