export type CalendarEventTypeValue =
    | 'action'
    | 'task'
    | 'meeting'
    | 'personal'
    | 'reminder';

export type CalendarEventPriorityValue =
    | 'critical'
    | 'important'
    | 'standard'
    | 'none';

export type CalendarEventTheme = {
    chipClass: string;
    borderClass: string;
};

export const CALENDAR_EVENT_TYPE_THEMES: Record<
    CalendarEventTypeValue,
    CalendarEventTheme
> = {
    action: {
        chipClass:
            'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-slate-100',
        borderClass: 'border-l-slate-500',
    },
    task: {
        chipClass:
            'bg-blue-100 text-blue-900 dark:bg-blue-950 dark:text-blue-100',
        borderClass: 'border-l-blue-500',
    },
    meeting: {
        chipClass:
            'bg-violet-100 text-violet-900 dark:bg-violet-950 dark:text-violet-100',
        borderClass: 'border-l-violet-500',
    },
    personal: {
        chipClass:
            'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100',
        borderClass: 'border-l-emerald-500',
    },
    reminder: {
        chipClass:
            'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-100',
        borderClass: 'border-l-amber-500',
    },
};

export const CALENDAR_EVENT_PRIORITY_BORDER: Record<
    CalendarEventPriorityValue,
    string
> = {
    critical: 'ring-1 ring-red-500/60',
    important: 'ring-1 ring-orange-500/50',
    standard: '',
    none: 'opacity-90',
};

export function calendarEventTheme(type: CalendarEventTypeValue): CalendarEventTheme {
    return CALENDAR_EVENT_TYPE_THEMES[type];
}
