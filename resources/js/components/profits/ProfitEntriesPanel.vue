<script setup lang="ts">
import { Info, Pencil, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslations } from '@/composables/useTranslations';
import type { ProfitEntryItem } from '@/types/profits';

type Props = {
    title: string;
    entries: ProfitEntryItem[];
    emptyMessage: string;
    formatAmount: (value: string) => string;
};

defineProps<Props>();

const emit = defineEmits<{
    edit: [entry: ProfitEntryItem];
    delete: [entry: ProfitEntryItem];
}>();

const { t } = useTranslations();

const showInfoDialog = ref(false);
const infoEntry = ref<ProfitEntryItem | null>(null);

const openInfo = (entry: ProfitEntryItem): void => {
    infoEntry.value = entry;
    showInfoDialog.value = true;
};

const closeInfo = (): void => {
    showInfoDialog.value = false;
    infoEntry.value = null;
};
</script>

<template>
    <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border">
        <div
            class="shrink-0 border-b bg-muted/30 px-4 py-3 text-sm font-semibold tracking-tight"
        >
            {{ title }}
            <span class="ml-1 font-normal text-muted-foreground"
                >({{ entries.length }})</span
            >
        </div>

        <div class="min-h-0 flex-1 overflow-auto">
            <table class="w-full min-w-md text-sm">
                <thead
                    class="sticky top-0 z-1 border-b bg-background text-left text-muted-foreground"
                >
                    <tr>
                        <th class="px-3 py-2 font-medium">
                            {{ t('profits.columns.date') }}
                        </th>
                        <th class="px-3 py-2 font-medium">
                            {{ t('profits.columns.document_number') }}
                        </th>
                        <th class="px-3 py-2 font-medium">
                            {{ t('profits.columns.type') }}
                        </th>
                        <th class="px-3 py-2 text-right font-medium">
                            {{ t('profits.columns.amount') }}
                        </th>
                        <th class="px-3 py-2 text-right font-medium">
                            {{ t('profits.columns.actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="entries.length === 0">
                        <td
                            colspan="5"
                            class="px-3 py-10 text-center text-muted-foreground"
                        >
                            {{ emptyMessage }}
                        </td>
                    </tr>
                    <tr
                        v-for="entry in entries"
                        :key="entry.id"
                        class="border-b last:border-b-0"
                    >
                        <td class="px-3 py-2 whitespace-nowrap">
                            {{ entry.date }}
                        </td>
                        <td class="px-3 py-2">
                            {{ entry.document_number || '—' }}
                        </td>
                        <td class="px-3 py-2">
                            {{ entry.type.name }}
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            {{ formatAmount(entry.amount) }}
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    v-if="entry.description"
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-8"
                                    :aria-label="t('profits.info')"
                                    @click="openInfo(entry)"
                                >
                                    <Info class="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-8"
                                    :aria-label="t('common.edit')"
                                    @click="emit('edit', entry)"
                                >
                                    <Pencil class="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 text-destructive hover:text-destructive"
                                    :aria-label="t('common.delete')"
                                    @click="emit('delete', entry)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Dialog :open="showInfoDialog" @update:open="showInfoDialog = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ t('profits.info_title') }}</DialogTitle>
                    <DialogDescription v-if="infoEntry">
                        {{ infoEntry.type.name }} · {{ infoEntry.date }}
                    </DialogDescription>
                </DialogHeader>

                <p
                    v-if="infoEntry?.description"
                    class="whitespace-pre-wrap text-sm text-foreground"
                >
                    {{ infoEntry.description }}
                </p>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="closeInfo">
                        {{ t('common.close') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </section>
</template>
