<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
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
import { useAppToast } from '@/composables/useAppToast';
import { useTranslations } from '@/composables/useTranslations';
import type { ProjectListItem, ProjectStatus } from '@/pages/projects/columns';
import { store, update } from '@/routes/projects';

type Props = {
    open: boolean;
    mode: 'create' | 'edit';
    project?: ProjectListItem | null;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [];
}>();

const { t } = useTranslations();
const { showError, showMessage } = useAppToast();

const emptyFormState = {
    name: '',
    description: '',
    status: 'active' as ProjectStatus,
    expected_completion_at: '',
};

const form = useForm({ ...emptyFormState });

const dialogTitle = computed(() =>
    props.mode === 'create'
        ? t('projects.create_title')
        : t('projects.edit_title'),
);

const statusOptions: ProjectStatus[] = ['active', 'completed', 'deferred'];

const clearForm = (): void => {
    form.defaults({ ...emptyFormState });
    form.reset();
    form.clearErrors();
};

watch(
    () => [props.open, props.mode, props.project] as const,
    ([open, mode, project]) => {
        if (!open) {
            return;
        }

        clearForm();

        if (mode === 'edit' && project) {
            form.name = project.name;
            form.description = project.description ?? '';
            form.status = project.status;
            form.expected_completion_at =
                project.expected_completion_at?.slice(0, 10) ?? '';
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

const close = (): void => {
    emit('update:open', false);
};

const submit = (): void => {
    const payload = {
        name: form.name,
        description: form.description || null,
        status: form.status,
        expected_completion_at: form.expected_completion_at || null,
    };

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showMessage(t('common.success'), dialogTitle.value);
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
        form.transform(() => payload).post(store().url, options);

        return;
    }

    if (props.project) {
        form.transform(() => payload).put(update(props.project.id).url, options);
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[90vh] max-w-lg overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="project-name">{{ t('projects.fields.name') }}</Label>
                    <Input
                        id="project-name"
                        v-model="form.name"
                        maxlength="120"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="project-description">{{
                        t('projects.fields.description')
                    }}</Label>
                    <textarea
                        id="project-description"
                        v-model="form.description"
                        rows="4"
                        class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="grid gap-2">
                    <Label>{{ t('projects.fields.status') }}</Label>
                    <Select v-model="form.status">
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="status in statusOptions"
                                :key="status"
                                :value="status"
                            >
                                {{ t(`projects.status.${status}`) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.status" />
                </div>

                <div class="grid gap-2">
                    <Label for="project-expected">{{
                        t('projects.fields.expected_completion_at')
                    }}</Label>
                    <Input
                        id="project-expected"
                        v-model="form.expected_completion_at"
                        type="date"
                    />
                    <InputError :message="form.errors.expected_completion_at" />
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="close">
                        {{ t('common.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ t('common.save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
