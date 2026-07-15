<?php

namespace App\Http\Requests;

use App\Enums\CalendarEventPriority;
use App\Enums\CalendarEventStatus;
use App\Enums\CalendarEventType;
use App\Models\CalendarEvent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CalendarEvent::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'type' => ['nullable', Rule::enum(CalendarEventType::class)],
            'priority' => ['nullable', Rule::enum(CalendarEventPriority::class)],
            'status' => ['nullable', Rule::enum(CalendarEventStatus::class)],
        ];
    }
}
