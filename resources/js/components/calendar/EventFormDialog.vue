<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/composables/useTranslations';
import { toDateTimeLocalValue } from '@/lib/calendarDateUtils';
import type {
    CalendarEventFormData,
    CalendarEventItem,
    CalendarEventPriorityValue,
    CalendarEventStatusValue,
    CalendarEventTypeValue,
} from '@/types/calendar';

type Props = {
    open: boolean;
    mode: 'create' | 'edit';
    processing?: boolean;
    errors?: Record<string, string>;
    initialValues?: Partial<CalendarEventFormData>;
    event?: CalendarEventItem | null;
};

const props = withDefaults(defineProps<Props>(), {
    processing: false,
    errors: () => ({}),
});

const emit = defineEmits<{
    'update:open': [value: boolean];
    submit: [payload: CalendarEventFormData];
}>();

const { t } = useTranslations();

const typeOptions: CalendarEventTypeValue[] = [
    'action',
    'task',
    'meeting',
    'personal',
    'reminder',
];

const priorityOptions: CalendarEventPriorityValue[] = [
    'critical',
    'important',
    'standard',
    'none',
];

const statusOptions: CalendarEventStatusValue[] = ['active', 'completed'];

const form = reactive<CalendarEventFormData>({
    title: '',
    description: '',
    starts_at: '',
    ends_at: '',
    type: 'action',
    priority: 'standard',
    status: 'active',
});

const dialogTitle = computed(() =>
    props.mode === 'create'
        ? t('calendar.create_title')
        : t('calendar.edit_title'),
);

watch(
    () => [props.open, props.mode, props.initialValues, props.event] as const,
    ([open]) => {
        if (!open) {
            return;
        }

        if (props.mode === 'edit' && props.event) {
            form.title = props.event.title;
            form.description = props.event.description ?? '';
            form.starts_at = toDateTimeLocalValue(props.event.starts_at);
            form.ends_at = toDateTimeLocalValue(props.event.ends_at);
            form.type = props.event.type;
            form.priority = props.event.priority;
            form.status = props.event.status;

            return;
        }

        form.title = props.initialValues?.title ?? '';
        form.description = props.initialValues?.description ?? '';
        form.starts_at = props.initialValues?.starts_at
            ? toDateTimeLocalValue(props.initialValues.starts_at)
            : '';
        form.ends_at = props.initialValues?.ends_at
            ? toDateTimeLocalValue(props.initialValues.ends_at)
            : '';
        form.type = props.initialValues?.type ?? 'action';
        form.priority = props.initialValues?.priority ?? 'standard';
        form.status = props.initialValues?.status ?? 'active';
    },
    { immediate: true },
);

const close = (): void => {
    emit('update:open', false);
};

const submit = (): void => {
    emit('submit', { ...form });
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="calendar-title">{{ t('calendar.fields.title') }}</Label>
                    <Input
                        id="calendar-title"
                        v-model="form.title"
                        maxlength="120"
                        required
                        :placeholder="t('calendar.fields.title_placeholder')"
                    />
                    <InputError :message="errors.title" />
                </div>

                <div class="grid gap-2">
                    <Label for="calendar-description">{{
                        t('calendar.fields.description')
                    }}</Label>
                    <textarea
                        id="calendar-description"
                        v-model="form.description"
                        rows="3"
                        :placeholder="t('calendar.fields.description_placeholder')"
                        class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="calendar-starts-at">{{
                            t('calendar.fields.starts_at')
                        }}</Label>
                        <Input
                            id="calendar-starts-at"
                            v-model="form.starts_at"
                            type="datetime-local"
                            required
                        />
                        <InputError :message="errors.starts_at" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="calendar-ends-at">{{
                            t('calendar.fields.ends_at')
                        }}</Label>
                        <Input
                            id="calendar-ends-at"
                            v-model="form.ends_at"
                            type="datetime-local"
                            required
                        />
                        <InputError :message="errors.ends_at" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label>{{ t('calendar.fields.type') }}</Label>
                        <Select v-model="form.type">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in typeOptions"
                                    :key="option"
                                    :value="option"
                                >
                                    {{ t(`calendar.type.${option}`) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.type" />
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ t('calendar.fields.priority') }}</Label>
                        <Select v-model="form.priority">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in priorityOptions"
                                    :key="option"
                                    :value="option"
                                >
                                    {{ t(`calendar.priority.${option}`) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.priority" />
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ t('calendar.fields.status') }}</Label>
                        <Select v-model="form.status">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in statusOptions"
                                    :key="option"
                                    :value="option"
                                >
                                    {{ t(`calendar.status.${option}`) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.status" />
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="close">
                        {{ t('common.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="processing">
                        {{ t('common.save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
