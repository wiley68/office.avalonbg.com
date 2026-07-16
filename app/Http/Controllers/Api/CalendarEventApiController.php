<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CalendarEventApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CalendarEvent::class);

        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->endOfDay();

        $events = CalendarEvent::query()
            ->forUser($user->id)
            ->overlapping($from, $to)
            ->orderBy('starts_at')
            ->get()
            ->map(fn (CalendarEvent $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                // Naive local wall-clock times (no Z/+00:00) so the browser
                // does not shift Europe/Sofia display against APP_TIMEZONE=UTC.
                'starts_at' => $event->starts_at->format('Y-m-d\\TH:i:s'),
                'ends_at' => $event->ends_at->format('Y-m-d\\TH:i:s'),
                'type' => $event->type->value,
                'priority' => $event->priority->value,
                'status' => $event->status->value,
                'completed_at' => $event->completed_at?->format('Y-m-d\\TH:i:s'),
                'created_at' => $event->created_at?->toIso8601String(),
            ]);

        return response()->json($events);
    }
}
