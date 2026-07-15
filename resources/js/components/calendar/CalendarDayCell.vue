<script setup lang="ts">
import { cn } from '@/lib/utils';
import { isDayInRange } from '@/lib/calendarDateUtils';
import type { CalendarDay } from '@/lib/calendarDateUtils';

type Props = {
    day: CalendarDay;
    selectionStart?: string | null;
    selectionEnd?: string | null;
};

withDefaults(defineProps<Props>(), {
    selectionStart: null,
    selectionEnd: null,
});

const emit = defineEmits<{
    mousedown: [];
    mouseenter: [];
    mouseup: [];
}>();
</script>

<template>
    <div
        class="min-h-28 border-r p-1 select-none last:border-r-0"
        :class="
            cn(
                'bg-background transition-colors',
                !day.isCurrentMonth && 'bg-muted/20',
                isDayInRange(day.iso, selectionStart ?? null, selectionEnd ?? null) &&
                    'bg-primary/10',
                day.isToday && 'ring-1 ring-inset ring-primary/40',
            )
        "
        @mousedown.prevent="emit('mousedown')"
        @mouseenter="emit('mouseenter')"
        @mouseup="emit('mouseup')"
    >
        <div
            class="mb-1 flex justify-end px-1 text-xs tabular-nums"
            :class="
                day.isCurrentMonth
                    ? day.isToday
                        ? 'font-semibold text-primary'
                        : 'text-foreground'
                    : 'text-muted-foreground/60'
            "
        >
            {{ day.date.date() }}
        </div>

        <div class="space-y-0.5">
            <slot />
        </div>
    </div>
</template>
