<?php

namespace Database\Factories;

use App\Enums\CalendarEventPriority;
use App\Enums\CalendarEventStatus;
use App\Enums\CalendarEventType;
use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarEvent>
 */
class CalendarEventFactory extends Factory
{
    protected $model = CalendarEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('-1 month', '+1 month');
        $endsAt = (clone $startsAt)->modify('+1 hour');

        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'type' => fake()->randomElement(CalendarEventType::cases()),
            'priority' => fake()->randomElement(CalendarEventPriority::cases()),
            'status' => CalendarEventStatus::Active,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => CalendarEventStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
