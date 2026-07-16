<?php

namespace App\Http\Controllers;

use App\Enums\CalendarEventPriority;
use App\Enums\CalendarEventStatus;
use App\Enums\CalendarEventType;
use App\Http\Requests\StoreCalendarEventRequest;
use App\Http\Requests\UpdateCalendarEventRequest;
use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', CalendarEvent::class);

        return Inertia::render('calendar/Index');
    }

    public function store(StoreCalendarEventRequest $request): RedirectResponse
    {
        $this->authorize('create', CalendarEvent::class);

        /** @var User $user */
        $user = Auth::user();

        $startsAt = $request->filled('starts_at')
            ? $this->parseWallClockDateTime($request->validated('starts_at'))
            : now();

        $endsAt = $request->filled('ends_at')
            ? $this->parseWallClockDateTime($request->validated('ends_at'))
            : $startsAt->copy()->addHour();

        $status = CalendarEventStatus::tryFrom((string) $request->input('status'))
            ?? CalendarEventStatus::Active;

        $user->calendarEvents()->create([
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'type' => CalendarEventType::tryFrom((string) $request->input('type'))
                ?? CalendarEventType::Action,
            'priority' => CalendarEventPriority::tryFrom((string) $request->input('priority'))
                ?? CalendarEventPriority::Standard,
            'status' => $status,
            'completed_at' => $status === CalendarEventStatus::Completed ? now() : null,
        ]);

        return back();
    }

    public function update(
        UpdateCalendarEventRequest $request,
        CalendarEvent $calendarEvent,
    ): RedirectResponse {
        $this->authorize('update', $calendarEvent);

        $status = CalendarEventStatus::from($request->validated('status'));

        $calendarEvent->update([
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'starts_at' => $this->parseWallClockDateTime($request->validated('starts_at')),
            'ends_at' => $this->parseWallClockDateTime($request->validated('ends_at')),
            'type' => CalendarEventType::from($request->validated('type')),
            'priority' => CalendarEventPriority::from($request->validated('priority')),
            'status' => $status,
            'completed_at' => $status === CalendarEventStatus::Completed
                ? ($calendarEvent->completed_at ?? now())
                : null,
        ]);

        return back();
    }

    public function destroy(CalendarEvent $calendarEvent): RedirectResponse
    {
        $this->authorize('delete', $calendarEvent);

        $calendarEvent->delete();

        return back();
    }

    /**
     * Parse datetime-local / naive wall-clock values without applying a
     * timezone conversion against APP_TIMEZONE (UTC).
     */
    private function parseWallClockDateTime(string $value): Carbon
    {
        $normalized = str_replace('T', ' ', trim($value));
        $normalized = preg_replace('/(Z|[+-]\d{2}:?\d{2})$/', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\.\d+$/', '', trim($normalized)) ?? $normalized;

        return Carbon::createFromFormat('Y-m-d H:i:s', strlen($normalized) === 16
            ? "{$normalized}:00"
            : $normalized) ?: Carbon::parse($value);
    }
}
