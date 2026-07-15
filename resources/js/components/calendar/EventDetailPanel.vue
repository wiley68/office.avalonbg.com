<script setup lang="ts">
import { Pencil, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useTranslations } from '@/composables/useTranslations';
import { formatDateTimeRange } from '@/lib/calendarDateUtils';
import { calendarEventTheme } from '@/lib/calendarEventTheme';
import type { CalendarEventItem } from '@/types/calendar';

type Props = {
    open: boolean;
    event: CalendarEventItem | null;
    locale: string;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    edit: [event: CalendarEventItem];
    delete: [event: CalendarEventItem];
    toggleComplete: [event: CalendarEventItem];
}>();

const { t } = useTranslations();

const theme = computed(() =>
    props.event ? calendarEventTheme(props.event.type) : null,
);

const periodLabel = computed(() => {
    if (!props.event) {
        return '';
    }

    return formatDateTimeRange(
        props.event.starts_at,
        props.event.ends_at,
        props.locale,
    );
});

const createdAtLabel = computed(() => {
    if (!props.event?.created_at) {
        return '—';
    }

    return new Date(props.event.created_at).toLocaleString(
        props.locale === 'bg' ? 'bg-BG' : 'en-GB',
    );
});
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent side="right" class="w-full sm:max-w-md">
            <SheetHeader v-if="event">
                <SheetTitle
                    :class="event.status === 'completed' && 'line-through opacity-70'"
                >
                    {{ event.title }}
                </SheetTitle>
                <SheetDescription>
                    {{ t('calendar.detail.subtitle') }}
                </SheetDescription>
            </SheetHeader>

            <div v-if="event" class="mt-6 space-y-5">
                <div class="flex flex-wrap gap-2">
                    <Badge :class="theme?.chipClass">
                        {{ t(`calendar.type.${event.type}`) }}
                    </Badge>
                    <Badge variant="outline">
                        {{ t(`calendar.priority.${event.priority}`) }}
                    </Badge>
                    <Badge
                        :variant="event.status === 'completed' ? 'secondary' : 'default'"
                    >
                        {{ t(`calendar.status.${event.status}`) }}
                    </Badge>
                </div>

                <div class="space-y-1">
                    <p class="text-xs font-medium text-muted-foreground uppercase">
                        {{ t('calendar.fields.period') }}
                    </p>
                    <p class="text-sm">{{ periodLabel }}</p>
                </div>

                <div class="space-y-1">
                    <p class="text-xs font-medium text-muted-foreground uppercase">
                        {{ t('calendar.fields.description') }}
                    </p>
                    <p class="text-sm whitespace-pre-wrap text-muted-foreground">
                        {{ event.description?.trim() || t('calendar.no_description') }}
                    </p>
                </div>

                <div class="space-y-1">
                    <p class="text-xs font-medium text-muted-foreground uppercase">
                        {{ t('calendar.fields.created_at') }}
                    </p>
                    <p class="text-sm">{{ createdAtLabel }}</p>
                </div>

                <div class="flex flex-wrap gap-2 pt-2">
                    <Button type="button" variant="outline" @click="emit('edit', event)">
                        <Pencil class="mr-2 h-4 w-4" />
                        {{ t('common.edit') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('toggleComplete', event)"
                    >
                        {{
                            event.status === 'completed'
                                ? t('calendar.mark_active')
                                : t('calendar.mark_completed')
                        }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="text-destructive"
                        @click="emit('delete', event)"
                    >
                        <Trash2 class="mr-2 h-4 w-4" />
                        {{ t('common.delete') }}
                    </Button>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
