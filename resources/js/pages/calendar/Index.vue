<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { useEventListener } from '@vueuse/core';
import { ChevronLeft, ChevronRight, Loader2, Plus } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import EventDetailPanel from '@/components/calendar/EventDetailPanel.vue';
import EventFormDialog from '@/components/calendar/EventFormDialog.vue';
import MonthGrid from '@/components/calendar/MonthGrid.vue';
import { Button } from '@/components/ui/button';
import { calendarEventsApiIndex } from '@/composables/useCalendarApiRoute';
import { useAppToast } from '@/composables/useAppToast';
import { useTranslations } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    buildMonthGrid,
    configureDayjsLocale,
    dayjs,
    getVisibleGridRange,
    normalizeRange,
    parseMonthFromUrl,
    rangeToDateTimes,
    formatMonthLabel,
} from '@/lib/calendarDateUtils';
import { dashboard } from '@/routes';
import { index as calendarIndex } from '@/routes/calendar';
import {
    destroy as destroyCalendarEvent,
    store as storeCalendarEvent,
    update as updateCalendarEvent,
} from '@/routes/calendar-events';
import type { BreadcrumbItem } from '@/types';
import type { CalendarEventFormData, CalendarEventItem } from '@/types/calendar';

const { t } = useTranslations();
const { showError, showMessage } = useAppToast();
const page = usePage();

const locale = computed(() =>
    typeof page.props.locale === 'string' ? page.props.locale : 'bg',
);

configureDayjsLocale(locale.value);

const visibleMonth = ref(parseMonthFromUrl(window.location.search));
const events = ref<CalendarEventItem[]>([]);
const loading = ref(false);
const formProcessing = ref(false);
const formErrors = ref<Record<string, string>>({});

const selectionAnchor = ref<string | null>(null);
const selectionFocus = ref<string | null>(null);
const isSelecting = ref(false);

const showFormDialog = ref(false);
const formMode = ref<'create' | 'edit'>('create');
const formInitialValues = ref<Partial<CalendarEventFormData>>({});

const showDetailPanel = ref(false);
const selectedEvent = ref<CalendarEventItem | null>(null);

const showDeleteDialog = ref(false);
const eventToDelete = ref<CalendarEventItem | null>(null);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('common.dashboard'), href: dashboard() },
    { title: t('calendar.title'), href: calendarIndex() },
]);

const weeks = computed(() => buildMonthGrid(visibleMonth.value));

const monthLabel = computed(() =>
    formatMonthLabel(visibleMonth.value, locale.value),
);

const weekdayLabels = computed(() => [
    t('calendar.weekdays.mon'),
    t('calendar.weekdays.tue'),
    t('calendar.weekdays.wed'),
    t('calendar.weekdays.thu'),
    t('calendar.weekdays.fri'),
    t('calendar.weekdays.sat'),
    t('calendar.weekdays.sun'),
]);

const selectionStart = computed(() => {
    if (selectionAnchor.value === null || selectionFocus.value === null) {
        return null;
    }

    return normalizeRange(selectionAnchor.value, selectionFocus.value).start;
});

const selectionEnd = computed(() => {
    if (selectionAnchor.value === null || selectionFocus.value === null) {
        return null;
    }

    return normalizeRange(selectionAnchor.value, selectionFocus.value).end;
});

const syncMonthToUrl = (): void => {
    const url = new URL(window.location.href);
    url.searchParams.set('month', visibleMonth.value.format('YYYY-MM'));
    window.history.replaceState({}, '', url.toString());
};

const fetchEvents = async (): Promise<void> => {
    loading.value = true;

    const { from, to } = getVisibleGridRange(visibleMonth.value);

    try {
        const response = await fetch(
            `${calendarEventsApiIndex().url}?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (!response.ok) {
            throw new Error('Failed to load calendar events');
        }

        events.value = (await response.json()) as CalendarEventItem[];
    } catch {
        showError(t('common.error'), t('calendar.errors.load_failed'));
    } finally {
        loading.value = false;
    }
};

const goToPreviousMonth = (): void => {
    visibleMonth.value = visibleMonth.value.subtract(1, 'month').startOf('month');
};

const goToNextMonth = (): void => {
    visibleMonth.value = visibleMonth.value.add(1, 'month').startOf('month');
};

const goToToday = (): void => {
    visibleMonth.value = dayjs().startOf('month');
};

const openCreateDialog = (initialValues?: Partial<CalendarEventFormData>): void => {
    formMode.value = 'create';
    const now = dayjs();
    formInitialValues.value = {
        starts_at: now.format('YYYY-MM-DDTHH:mm:ss'),
        ends_at: now.add(1, 'hour').format('YYYY-MM-DDTHH:mm:ss'),
        ...initialValues,
    };
    selectedEvent.value = null;
    formErrors.value = {};
    showFormDialog.value = true;
};

const openEditDialog = (event: CalendarEventItem): void => {
    formMode.value = 'edit';
    selectedEvent.value = event;
    formErrors.value = {};
    showDetailPanel.value = false;
    showFormDialog.value = true;
};

const finishSelection = (): void => {
    if (!isSelecting.value || selectionAnchor.value === null || selectionFocus.value === null) {
        isSelecting.value = false;

        return;
    }

    const { start, end } = normalizeRange(selectionAnchor.value, selectionFocus.value);
    const range = rangeToDateTimes(start, end);

    openCreateDialog({
        starts_at: range.starts_at,
        ends_at: range.ends_at,
    });

    selectionAnchor.value = null;
    selectionFocus.value = null;
    isSelecting.value = false;
};

const onDayMouseDown = (day: { iso: string }): void => {
    isSelecting.value = true;
    selectionAnchor.value = day.iso;
    selectionFocus.value = day.iso;
};

const onDayMouseEnter = (day: { iso: string }): void => {
    if (!isSelecting.value) {
        return;
    }

    selectionFocus.value = day.iso;
};

const onDayMouseUp = (): void => {
    finishSelection();
};

useEventListener(document, 'mouseup', () => {
    if (isSelecting.value) {
        finishSelection();
    }
});

const onEventClick = (event: CalendarEventItem): void => {
    selectedEvent.value = event;
    showDetailPanel.value = true;
};

const handleFormSubmit = (payload: CalendarEventFormData): void => {
    formProcessing.value = true;
    formErrors.value = {};

    const normalizedPayload = {
        ...payload,
        description: payload.description.trim() === '' ? null : payload.description,
    };

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showMessage(t('common.success'), t('calendar.saved'));
            showFormDialog.value = false;
            void fetchEvents();
        },
        onError: (errors: Record<string, string>) => {
            formErrors.value = errors;
            showError(t('common.error'), Object.values(errors).flat().join('\n'));
        },
        onFinish: () => {
            formProcessing.value = false;
        },
    };

    if (formMode.value === 'create') {
        router.post(storeCalendarEvent().url, normalizedPayload, options);

        return;
    }

    if (selectedEvent.value) {
        router.put(
            updateCalendarEvent(selectedEvent.value.id).url,
            normalizedPayload,
            options,
        );
    }
};

const requestDelete = (event: CalendarEventItem): void => {
    eventToDelete.value = event;
    showDeleteDialog.value = true;
    showDetailPanel.value = false;
};

const confirmDelete = (): void => {
    if (eventToDelete.value === null) {
        return;
    }

    const event = eventToDelete.value;
    eventToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(destroyCalendarEvent(event.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            showMessage(t('common.success'), t('calendar.deleted'));
            selectedEvent.value = null;
            void fetchEvents();
        },
        onError: (errors: Record<string, string>) => {
            showError(t('common.error'), Object.values(errors).flat().join('\n'));
        },
    });
};

const toggleComplete = (event: CalendarEventItem): void => {
    const nextStatus = event.status === 'completed' ? 'active' : 'completed';

    router.put(
        updateCalendarEvent(event.id).url,
        {
            title: event.title,
            description: event.description,
            starts_at: event.starts_at,
            ends_at: event.ends_at,
            type: event.type,
            priority: event.priority,
            status: nextStatus,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showMessage(t('common.success'), t('calendar.saved'));
                showDetailPanel.value = false;
                void fetchEvents();
            },
            onError: (errors: Record<string, string>) => {
                showError(t('common.error'), Object.values(errors).flat().join('\n'));
            },
        },
    );
};

watch(visibleMonth, () => {
    syncMonthToUrl();
    void fetchEvents();
});

watch(locale, (value) => {
    configureDayjsLocale(value);
});

onMounted(() => {
    void fetchEvents();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('calendar.title')" />

        <div class="space-y-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ t('calendar.title') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ t('calendar.subtitle') }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button type="button" variant="outline" size="sm" @click="goToToday">
                        {{ t('calendar.today') }}
                    </Button>
                    <Button type="button" variant="outline" size="icon" @click="goToPreviousMonth">
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                    <div class="min-w-40 text-center text-sm font-medium capitalize">
                        {{ monthLabel }}
                    </div>
                    <Button type="button" variant="outline" size="icon" @click="goToNextMonth">
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                    <Button type="button" size="sm" @click="openCreateDialog()">
                        <Plus class="mr-2 h-4 w-4" />
                        {{ t('calendar.add') }}
                    </Button>
                </div>
            </div>

            <div class="relative">
                <div
                    v-if="loading"
                    class="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-background/60"
                >
                    <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
                </div>

                <MonthGrid
                    :weeks="weeks"
                    :events="events"
                    :weekday-labels="weekdayLabels"
                    :week-label="t('calendar.week_short')"
                    :selection-start="selectionStart"
                    :selection-end="selectionEnd"
                    @day-mouse-down="onDayMouseDown"
                    @day-mouse-enter="onDayMouseEnter"
                    @day-mouse-up="onDayMouseUp"
                    @event-click="onEventClick"
                />
            </div>
        </div>

        <EventFormDialog
            v-model:open="showFormDialog"
            :mode="formMode"
            :event="selectedEvent"
            :initial-values="formInitialValues"
            :processing="formProcessing"
            :errors="formErrors"
            @submit="handleFormSubmit"
        />

        <EventDetailPanel
            v-model:open="showDetailPanel"
            :event="selectedEvent"
            :locale="locale"
            @edit="openEditDialog"
            @delete="requestDelete"
            @toggle-complete="toggleComplete"
        />

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            :title="t('users.delete_confirm_title')"
            :description="t('calendar.delete_confirm')"
            @confirm="confirmDelete"
            @cancel="eventToDelete = null; showDeleteDialog = false"
        />
    </AppLayout>
</template>
