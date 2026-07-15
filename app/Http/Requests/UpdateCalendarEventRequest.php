<?php

namespace App\Http\Requests;

use App\Enums\CalendarEventPriority;
use App\Enums\CalendarEventStatus;
use App\Enums\CalendarEventType;
use App\Models\CalendarEvent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $calendarEvent = $this->route('calendarEvent');

        return $calendarEvent instanceof CalendarEvent
            && ($this->user()?->can('update', $calendarEvent) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'type' => ['required', Rule::enum(CalendarEventType::class)],
            'priority' => ['required', Rule::enum(CalendarEventPriority::class)],
            'status' => ['required', Rule::enum(CalendarEventStatus::class)],
        ];
    }
}
