<script setup lang="ts">
import { ref, watch } from 'vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    loading?: boolean;
}>();

const emit = defineEmits<{
    confirm: [password: string, passwordConfirmation: string];
}>();

const { t } = useTranslations();

const password = ref('');
const passwordConfirmation = ref('');

watch(open, (isOpen) => {
    if (!isOpen) {
        password.value = '';
        passwordConfirmation.value = '';
    }
});

const handleConfirm = () => {
    emit('confirm', password.value, passwordConfirmation.value);
};
</script>

<template>
    <AlertDialog v-model:open="open">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{
                    t('audit_logs.export.dialog.title')
                }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ t('audit_logs.export.dialog.description') }}
                </AlertDialogDescription>
            </AlertDialogHeader>

            <div class="space-y-4 py-2">
                <div class="space-y-2">
                    <Label for="export-archive-password">{{
                        t('audit_logs.export.dialog.password')
                    }}</Label>
                    <Input
                        id="export-archive-password"
                        v-model="password"
                        type="password"
                        autocomplete="new-password"
                        :placeholder="
                            t('audit_logs.export.dialog.password_placeholder')
                        "
                    />
                </div>

                <div class="space-y-2">
                    <Label for="export-archive-password-confirmation">{{
                        t('audit_logs.export.dialog.confirm_password')
                    }}</Label>
                    <Input
                        id="export-archive-password-confirmation"
                        v-model="passwordConfirmation"
                        type="password"
                        autocomplete="new-password"
                        :placeholder="
                            t('audit_logs.export.dialog.confirm_placeholder')
                        "
                    />
                </div>
            </div>

            <AlertDialogFooter>
                <AlertDialogCancel :disabled="props.loading">{{
                    t('common.cancel')
                }}</AlertDialogCancel>
                <AlertDialogAction
                    :disabled="props.loading"
                    @click.prevent="handleConfirm"
                >
                    {{
                        props.loading
                            ? t('audit_logs.export.exporting')
                            : t('audit_logs.export.button')
                    }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
