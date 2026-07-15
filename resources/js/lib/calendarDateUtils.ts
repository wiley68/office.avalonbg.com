import dayjs, { type Dayjs } from 'dayjs';
import isoWeek from 'dayjs/plugin/isoWeek';
import isSameOrAfter from 'dayjs/plugin/isSameOrAfter';
import isSameOrBefore from 'dayjs/plugin/isSameOrBefore';
import 'dayjs/locale/bg';

dayjs.extend(isoWeek);
dayjs.extend(isSameOrAfter);
dayjs.extend(isSameOrBefore);

export type CalendarDay = {
    date: Dayjs;
    iso: string;
    isCurrentMonth: boolean;
    weekNumber: number;
    isToday: boolean;
};

export type CalendarWeekRow = {
    weekNumber: number;
    days: CalendarDay[];
};

export type CalendarEventLike = {
    id: number;
    title: string;
    starts_at: string;
    ends_at: string;
};

export function configureDayjsLocale(locale: string): void {
    dayjs.locale(locale === 'bg' ? 'bg' : 'en');
}

export function parseMonthFromUrl(search: string): Dayjs {
    const month = new URLSearchParams(search).get('month');

    if (month && /^\d{4}-\d{2}$/.test(month)) {
        const parsed = dayjs(`${month}-01`);

        if (parsed.isValid()) {
            return parsed.startOf('month');
        }
    }

    return dayjs().startOf('month');
}

export function buildMonthGrid(month: Dayjs): CalendarWeekRow[] {
    const startOfMonth = month.startOf('month');
    const endOfMonth = month.endOf('month');
    let cursor = startOfMonth.startOf('isoWeek');
    const rows: CalendarWeekRow[] = [];

    while (rows.length === 0 || cursor.isBefore(endOfMonth.endOf('isoWeek'))) {
        const days: CalendarDay[] = [];

        for (let index = 0; index < 7; index += 1) {
            const date = cursor.add(index, 'day');

            days.push({
                date,
                iso: date.format('YYYY-MM-DD'),
                isCurrentMonth: date.month() === month.month(),
                weekNumber: date.isoWeek(),
                isToday: date.isSame(dayjs(), 'day'),
            });
        }

        rows.push({
            weekNumber: days[0]?.weekNumber ?? cursor.isoWeek(),
            days,
        });

        cursor = cursor.add(7, 'day');

        if (rows.length >= 6 && cursor.isAfter(endOfMonth.endOf('isoWeek'))) {
            break;
        }
    }

    return rows;
}

export function getVisibleGridRange(month: Dayjs): { from: string; to: string } {
    const rows = buildMonthGrid(month);
    const firstDay = rows[0]?.days[0];
    const lastRow = rows[rows.length - 1];
    const lastDay = lastRow?.days[lastRow.days.length - 1];

    return {
        from: firstDay?.iso ?? month.startOf('month').format('YYYY-MM-DD'),
        to: lastDay?.iso ?? month.endOf('month').format('YYYY-MM-DD'),
    };
}

export function normalizeRange(startIso: string, endIso: string): {
    start: string;
    end: string;
} {
    const start = dayjs(startIso);
    const end = dayjs(endIso);

    if (start.isAfter(end)) {
        return { start: endIso, end: startIso };
    }

    return { start: startIso, end: endIso };
}

export function isDayInRange(
    dayIso: string,
    startIso: string | null,
    endIso: string | null,
): boolean {
    if (startIso === null || endIso === null) {
        return false;
    }

    const day = dayjs(dayIso);
    const start = dayjs(startIso);
    const end = dayjs(endIso);

    return day.isSameOrAfter(start, 'day') && day.isSameOrBefore(end, 'day');
}

export function eventOverlapsDay(event: CalendarEventLike, dayIso: string): boolean {
    const dayStart = dayjs(dayIso).startOf('day');
    const dayEnd = dayjs(dayIso).endOf('day');
    const startsAt = dayjs(event.starts_at);
    const endsAt = dayjs(event.ends_at);

    return startsAt.isBefore(dayEnd) && endsAt.isAfter(dayStart);
}

export function isEventStartDay(event: CalendarEventLike, dayIso: string): boolean {
    return dayjs(event.starts_at).format('YYYY-MM-DD') === dayIso;
}

export function rangeToDateTimes(startIso: string, endIso: string): {
    starts_at: string;
    ends_at: string;
} {
    const { start, end } = normalizeRange(startIso, endIso);

    if (start === end) {
        const startsAt = dayjs(start).hour(9).minute(0).second(0);

        return {
            starts_at: startsAt.format('YYYY-MM-DDTHH:mm:ss'),
            ends_at: startsAt.add(1, 'hour').format('YYYY-MM-DDTHH:mm:ss'),
        };
    }

    return {
        starts_at: dayjs(start).hour(9).minute(0).second(0).format('YYYY-MM-DDTHH:mm:ss'),
        ends_at: dayjs(end).hour(18).minute(0).second(0).format('YYYY-MM-DDTHH:mm:ss'),
    };
}

export function toDateTimeLocalValue(value: string): string {
    return dayjs(value).format('YYYY-MM-DDTHH:mm');
}

export function formatMonthLabel(month: Dayjs, locale: string): string {
    return month.locale(locale === 'bg' ? 'bg' : 'en').format('MMMM YYYY');
}

export function formatDateTimeRange(
    startsAt: string,
    endsAt: string,
    locale: string,
): string {
    const start = dayjs(startsAt).locale(locale === 'bg' ? 'bg' : 'en');
    const end = dayjs(endsAt).locale(locale === 'bg' ? 'bg' : 'en');

    if (start.isSame(end, 'day')) {
        return `${start.format('D MMM YYYY, HH:mm')} – ${end.format('HH:mm')}`;
    }

    return `${start.format('D MMM YYYY, HH:mm')} – ${end.format('D MMM YYYY, HH:mm')}`;
}

export { dayjs };
