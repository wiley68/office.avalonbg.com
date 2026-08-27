<script setup lang="ts">
import { Loader2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
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
import { useTranslations } from '@/composables/useTranslations';
import {
    downloadProfitExport,
    type ProfitExportFormat,
} from '@/lib/profitExport';
import { validateLogExportDateRange } from '@/lib/logExportDateRange';

type Props = {
    open: boolean;
    exportUrl: string;
    format: ProfitExportFormat;
    defaultDateFrom: string;
    defaultDateTo: string;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    success: [filename: string];
    error: [message: string];
}>();

const { t } = useTranslations();

const dateFrom = ref(props.defaultDateFrom);
const dateTo = ref(props.defaultDateTo);
const isExporting = ref(false);
const localError = ref<string | null>(null);

watch(
    () => [props.open, props.defaultDateFrom, props.defaultDateTo] as const,
    ([open, from, to]) => {
        if (!open) {
            return;
        }

        dateFrom.value = from;
        dateTo.value = to;
        localError.value = null;
    },
);

const close = (): void => {
    emit('update:open', false);
};

const submit = async (): Promise<void> => {
    localError.value = null;

    const validationError = validateLogExportDateRange(
        dateFrom.value,
        dateTo.value,
    );

    if (validationError) {
        localError.value = t(validationError);

        return;
    }

    isExporting.value = true;

    try {
        const result = await downloadProfitExport(
            props.exportUrl,
            dateFrom.value,
            dateTo.value,
            props.format,
        );

        if (!result.ok) {
            localError.value = result.message;
            emit('error', result.message);

            return;
        }

        emit('success', result.filename);
        close();
    } catch {
        const message = t('profits.export.error');
        localError.value = message;
        emit('error', message);
    } finally {
        isExporting.value = false;
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{
                        format === 'xlsx'
                            ? t('profits.export.xlsx_title')
                            : t('profits.export.pdf_title')
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('profits.export.dialog_description') }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="profit-export-from">{{
                        t('profits.export.date_from')
                    }}</Label>
                    <Input
                        id="profit-export-from"
                        v-model="dateFrom"
                        type="date"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="profit-export-to">{{
                        t('profits.export.date_to')
                    }}</Label>
                    <Input
                        id="profit-export-to"
                        v-model="dateTo"
                        type="date"
                    />
                </div>
                <p v-if="localError" class="text-sm text-destructive">
                    {{ localError }}
                </p>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="isExporting"
                    @click="close"
                >
                    {{ t('common.cancel') }}
                </Button>
                <Button
                    type="button"
                    :disabled="isExporting"
                    @click="submit"
                >
                    <Loader2
                        v-if="isExporting"
                        class="mr-2 size-4 animate-spin"
                    />
                    {{
                        isExporting
                            ? t('profits.export.exporting')
                            : t('profits.export.confirm')
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
