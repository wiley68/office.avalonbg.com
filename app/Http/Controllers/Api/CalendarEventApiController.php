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
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at->toIso8601String(),
                'type' => $event->type->value,
                'priority' => $event->priority->value,
                'status' => $event->status->value,
                'completed_at' => $event->completed_at?->toIso8601String(),
                'created_at' => $event->created_at?->toIso8601String(),
            ]);

        return response()->json($events);
    }
}
