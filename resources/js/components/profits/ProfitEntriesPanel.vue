<script setup lang="ts">
import { Pencil, Trash2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
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
    </section>
</template>
