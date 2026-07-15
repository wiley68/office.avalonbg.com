<script setup lang="ts">
import CalendarDayCell from '@/components/calendar/CalendarDayCell.vue';
import CalendarEventChip from '@/components/calendar/CalendarEventChip.vue';
import type { CalendarDay, CalendarWeekRow } from '@/lib/calendarDateUtils';
import { eventOverlapsDay } from '@/lib/calendarDateUtils';
import type { CalendarEventItem } from '@/types/calendar';

type Props = {
    weeks: CalendarWeekRow[];
    events: CalendarEventItem[];
    weekdayLabels: string[];
    weekLabel: string;
    selectionStart: string | null;
    selectionEnd: string | null;
};

defineProps<Props>();

const emit = defineEmits<{
    dayMouseDown: [day: CalendarDay];
    dayMouseEnter: [day: CalendarDay];
    dayMouseUp: [day: CalendarDay];
    eventClick: [event: CalendarEventItem];
}>();

const eventsForDay = (
    day: CalendarDay,
    events: CalendarEventItem[],
): CalendarEventItem[] => {
    return events.filter((event) => eventOverlapsDay(event, day.iso));
};
</script>

<template>
    <div class="overflow-hidden rounded-xl border bg-background shadow-sm">
        <div
            class="grid border-b bg-muted/40 text-xs font-medium text-muted-foreground"
            style="grid-template-columns: 3rem repeat(7, minmax(0, 1fr))"
        >
            <div class="border-r px-2 py-2 text-center">{{ weekLabel }}</div>
            <div
                v-for="label in weekdayLabels"
                :key="label"
                class="border-r px-2 py-2 text-center last:border-r-0"
            >
                {{ label }}
            </div>
        </div>

        <div
            v-for="week in weeks"
            :key="`${week.weekNumber}-${week.days[0]?.iso}`"
            class="grid border-b last:border-b-0"
            style="grid-template-columns: 3rem repeat(7, minmax(0, 1fr))"
        >
            <div
                class="flex items-start justify-center border-r bg-muted/20 px-1 py-2 text-xs tabular-nums text-muted-foreground"
            >
                {{ week.weekNumber }}
            </div>

            <CalendarDayCell
                v-for="day in week.days"
                :key="day.iso"
                :day="day"
                :selection-start="selectionStart"
                :selection-end="selectionEnd"
                @mousedown="emit('dayMouseDown', day)"
                @mouseenter="emit('dayMouseEnter', day)"
                @mouseup="emit('dayMouseUp', day)"
            >
                <CalendarEventChip
                    v-for="event in eventsForDay(day, events)"
                    :key="`${event.id}-${day.iso}`"
                    :event="event"
                    :day-iso="day.iso"
                    @click="emit('eventClick', event)"
                />
            </CalendarDayCell>
        </div>
    </div>
</template>
