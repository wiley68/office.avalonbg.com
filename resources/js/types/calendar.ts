import type {
    CalendarEventPriorityValue,
    CalendarEventTypeValue,
} from '@/lib/calendarEventTheme';

export type { CalendarEventPriorityValue, CalendarEventTypeValue };

export type CalendarEventStatusValue = 'active' | 'completed';

export type CalendarEventItem = {
    id: number;
    title: string;
    description: string | null;
    starts_at: string;
    ends_at: string;
    type: CalendarEventTypeValue;
    priority: CalendarEventPriorityValue;
    status: CalendarEventStatusValue;
    completed_at: string | null;
    created_at: string | null;
};

export type CalendarEventFormData = {
    title: string;
    description: string;
    starts_at: string;
    ends_at: string;
    type: CalendarEventTypeValue;
    priority: CalendarEventPriorityValue;
    status: CalendarEventStatusValue;
};
