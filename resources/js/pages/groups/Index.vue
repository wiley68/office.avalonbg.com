<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useAppToast } from '@/composables/useAppToast';
import { useTranslations } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { destroy, index, store, update } from '@/routes/groups';
import type { BreadcrumbItem } from '@/types';

type Group = { id: number; name: string; users_count: number };
defineProps<{ groups: Group[] }>();
const { t } = useTranslations();
const { showError } = useAppToast();
const form = useForm({ name: '' });
const editingId = ref<number | null>(null);
const showForm = ref(false);
const deletingGroup = ref<Group | null>(null);
const showDelete = ref(false);
const deleting = ref(false);
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('common.dashboard'), href: dashboard() },
    { title: t('groups.title'), href: index() },
]);

function openForm(group?: Group): void {
    editingId.value = group?.id ?? null;
    form.name = group?.name ?? '';
    form.clearErrors();
    showForm.value = true;
}

function submit(): void {
    const options = {
        onSuccess: () => {
            showForm.value = false;
        },
    };

    if (editingId.value === null) {
        form.post(store().url, options);
    } else {
        form.put(update(editingId.value).url, options);
    }
}

function requestDelete(group: Group): void {
    deletingGroup.value = group;
    showDelete.value = true;
}

function confirmDelete(): void {
    if (!deletingGroup.value) {
        return;
    }

    deleting.value = true;
    router.delete(destroy(deletingGroup.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            showDelete.value = false;
        },
        onError: (errors) => {
            showDelete.value = false;
            showError(t('common.error'), Object.values(errors).join('\n'));
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('groups.title')" />
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between gap-4">
                <h1 class="text-xl font-semibold">{{ t('groups.title') }}</h1>
                <Button @click="openForm()">{{ t('groups.create') }}</Button>
            </div>
            <div class="rounded-xl border p-4 shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ t('common.name') }}</TableHead>
                            <TableHead>{{ t('groups.members') }}</TableHead>
                            <TableHead>{{
                                t('users.columns.actions')
                            }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="group in groups" :key="group.id">
                            <TableCell>{{ group.name }}</TableCell>
                            <TableCell>{{ group.users_count }}</TableCell>
                            <TableCell>
                                <div class="flex gap-2">
                                    <Button
                                        variant="outline"
                                        @click="openForm(group)"
                                        >{{ t('common.edit') }}</Button
                                    >
                                    <Button
                                        variant="destructive"
                                        @click="requestDelete(group)"
                                        >{{ t('common.delete') }}</Button
                                    >
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="groups.length === 0">
                            <TableCell
                                :colspan="3"
                                class="text-center text-muted-foreground"
                                >{{ t('groups.empty') }}</TableCell
                            >
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
        <Dialog v-model:open="showForm">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{
                        editingId === null
                            ? t('groups.create')
                            : t('groups.rename')
                    }}</DialogTitle>
                    <DialogDescription>{{
                        t('groups.form_description')
                    }}</DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="space-y-2">
                        <Label for="group-name">{{ t('common.name') }}</Label>
                        <Input
                            id="group-name"
                            v-model="form.name"
                            required
                            maxlength="255"
                            :aria-invalid="!!form.errors.name"
                            aria-describedby="group-name-error"
                        />
                        <InputError
                            id="group-name-error"
                            :message="form.errors.name"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showForm = false"
                            >{{ t('common.cancel') }}</Button
                        >
                        <Button type="submit" :disabled="form.processing">{{
                            t('common.save')
                        }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
        <AppAlertDialog
            v-model:open="showDelete"
            :title="t('groups.delete_title')"
            :description="
                t('groups.delete_confirm', { name: deletingGroup?.name ?? '' })
            "
            :loading="deleting"
            @confirm="confirmDelete"
            @cancel="showDelete = false"
        />
    </AppLayout>
</template>
