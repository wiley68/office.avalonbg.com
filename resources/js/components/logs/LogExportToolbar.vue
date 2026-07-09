<script setup lang="ts">
import { FileDown, Loader2 } from 'lucide-vue-next';
import { ref } from 'vue';
import EncryptedExportDialog from '@/components/exports/EncryptedExportDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/composables/useTranslations';
import { downloadEncryptedExport } from '@/lib/encryptedExport';
import {
    getDefaultLogExportDateRange,
    validateLogExportDateRange,
} from '@/lib/logExportDateRange';

const props = defineProps<{
    exportUrl: string;
}>();

const { t } = useTranslations();

const defaultRange = getDefaultLogExportDateRange();
const dateFrom = ref(defaultRange.dateFrom);
const dateTo = ref(defaultRange.dateTo);
const isExporting = ref(false);
const showExportDialog = ref(false);
const exportError = ref<string | null>(null);
const exportSuccess = ref<string | null>(null);

const openExportDialog = () => {
    exportError.value = null;
    exportSuccess.value = null;

    const validationError = validateLogExportDateRange(
        dateFrom.value,
        dateTo.value,
    );

    if (validationError) {
        exportError.value = t(validationError);

        return;
    }

    showExportDialog.value = true;
};

const handleExport = async (
    password: string,
    passwordConfirmation: string,
) => {
    isExporting.value = true;
    exportError.value = null;

    try {
        const result = await downloadEncryptedExport(
            props.exportUrl,
            password,
            passwordConfirmation,
            {
                date_from: dateFrom.value,
                date_to: dateTo.value,
            },
        );

        if (!result.ok) {
            exportError.value = result.message;

            return;
        }

        showExportDialog.value = false;
        exportSuccess.value = t('audit_logs.export.success', {
            filename: result.filename,
        });
    } catch {
        exportError.value = t('audit_logs.export.error');
    } finally {
        isExporting.value = false;
    }
};
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex flex-wrap items-end gap-2">
            <div class="space-y-1">
                <Label
                    for="log-export-date-from"
                    class="text-xs text-muted-foreground"
                >
                    {{ t('audit_logs.export.date_from') }}
                </Label>
                <Input
                    id="log-export-date-from"
                    v-model="dateFrom"
                    type="date"
                    class="w-42"
                />
            </div>

            <div class="space-y-1">
                <Label
                    for="log-export-date-to"
                    class="text-xs text-muted-foreground"
                >
                    {{ t('audit_logs.export.date_to') }}
                </Label>
                <Input
                    id="log-export-date-to"
                    v-model="dateTo"
                    type="date"
                    class="w-42"
                />
            </div>

            <Button
                variant="secondary"
                :disabled="isExporting"
                type="button"
                @click="openExportDialog"
            >
                <Loader2
                    v-if="isExporting"
                    class="mr-2 h-4 w-4 animate-spin"
                />
                <FileDown v-else class="mr-2 h-4 w-4" />
                {{ t('audit_logs.export.button') }}
            </Button>
        </div>

        <p v-if="exportError" class="text-sm text-destructive">
            {{ exportError }}
        </p>
        <p v-if="exportSuccess" class="text-sm text-muted-foreground">
            {{ exportSuccess }}
        </p>

        <EncryptedExportDialog
            v-model:open="showExportDialog"
            :loading="isExporting"
            @confirm="handleExport"
        />
    </div>
</template>
