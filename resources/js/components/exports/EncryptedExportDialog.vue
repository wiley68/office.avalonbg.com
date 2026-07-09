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

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    loading?: boolean;
}>();

const emit = defineEmits<{
    confirm: [password: string, passwordConfirmation: string];
}>();

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
                <AlertDialogTitle>Парола за криптиран архив</AlertDialogTitle>
                <AlertDialogDescription>
                    Експортът ще бъде пакетиран в криптиран 7z архив. Запомнете
                    паролата — тя не се съхранява в системата и е необходима за
                    отваряне на файла.
                </AlertDialogDescription>
            </AlertDialogHeader>

            <div class="space-y-4 py-2">
                <div class="space-y-2">
                    <Label for="export-archive-password">Парола</Label>
                    <Input
                        id="export-archive-password"
                        v-model="password"
                        type="password"
                        autocomplete="new-password"
                        placeholder="Въведете парола"
                    />
                </div>

                <div class="space-y-2">
                    <Label for="export-archive-password-confirmation"
                        >Потвърди парола</Label
                    >
                    <Input
                        id="export-archive-password-confirmation"
                        v-model="passwordConfirmation"
                        type="password"
                        autocomplete="new-password"
                        placeholder="Повторете паролата"
                    />
                </div>
            </div>

            <AlertDialogFooter>
                <AlertDialogCancel :disabled="props.loading"
                    >Отказ</AlertDialogCancel
                >
                <AlertDialogAction
                    :disabled="props.loading"
                    @click.prevent="handleConfirm"
                >
                    {{ props.loading ? 'Експорт...' : 'Експорт 7z' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
