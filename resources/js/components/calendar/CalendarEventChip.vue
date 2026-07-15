<script setup lang="ts">
import { cn } from '@/lib/utils';
import {
    CALENDAR_EVENT_PRIORITY_BORDER,
    calendarEventTheme,
} from '@/lib/calendarEventTheme';
import { isEventStartDay } from '@/lib/calendarDateUtils';
import type { CalendarEventItem } from '@/types/calendar';

type Props = {
    event: CalendarEventItem;
    dayIso: string;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    click: [];
}>();

const theme = calendarEventTheme(props.event.type);
const showTitle = isEventStartDay(props.event, props.dayIso);
</script>

<template>
    <button
        type="button"
        class="block w-full truncate rounded border-l-2 px-1 py-0.5 text-left text-[11px] leading-tight"
        :class="
            cn(
                theme.chipClass,
                theme.borderClass,
                CALENDAR_EVENT_PRIORITY_BORDER[event.priority],
                event.status === 'completed' && 'line-through opacity-60',
            )
        "
        @mousedown.stop
        @click.stop="emit('click')"
    >
        {{ showTitle ? event.title : '…' }}
    </button>
</template>
