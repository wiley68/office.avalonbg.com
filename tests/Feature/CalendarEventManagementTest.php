<?php

use App\Enums\CalendarEventPriority;
use App\Enums\CalendarEventStatus;
use App\Enums\CalendarEventType;
use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('user', 'web');
});

test('office user can create update and delete calendar events', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user);

    Carbon::setTestNow('2026-07-15 10:00:00');

    post(route('calendar-events.store'), [
        'title' => 'Client meeting',
        'description' => 'Discuss scope',
    ])->assertRedirect();

    $event = CalendarEvent::query()->firstOrFail();

    expect($event->title)->toBe('Client meeting')
        ->and($event->description)->toBe('Discuss scope')
        ->and($event->type)->toBe(CalendarEventType::Action)
        ->and($event->priority)->toBe(CalendarEventPriority::Standard)
        ->and($event->status)->toBe(CalendarEventStatus::Active)
        ->and($event->starts_at->toDateTimeString())->toBe('2026-07-15 10:00:00')
        ->and($event->ends_at->toDateTimeString())->toBe('2026-07-15 11:00:00');

    put(route('calendar-events.update', $event), [
        'title' => 'Updated meeting',
        'description' => 'New notes',
        'starts_at' => '2026-07-16 09:00:00',
        'ends_at' => '2026-07-16 10:30:00',
        'type' => CalendarEventType::Meeting->value,
        'priority' => CalendarEventPriority::Important->value,
        'status' => CalendarEventStatus::Active->value,
    ])->assertRedirect();

    expect($event->fresh())
        ->title->toBe('Updated meeting')
        ->type->toBe(CalendarEventType::Meeting)
        ->priority->toBe(CalendarEventPriority::Important);

    delete(route('calendar-events.destroy', $event))->assertRedirect();

    expect(CalendarEvent::query()->count())->toBe(0);

    Carbon::setTestNow();
});

test('calendar event store validates ends_at is after starts_at', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user);

    post(route('calendar-events.store'), [
        'title' => 'Invalid event',
        'starts_at' => '2026-07-20 12:00:00',
        'ends_at' => '2026-07-20 10:00:00',
    ])->assertSessionHasErrors('ends_at');
});

test('completing calendar event sets completed_at', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $event = CalendarEvent::factory()->for($user)->create([
        'starts_at' => '2026-07-10 09:00:00',
        'ends_at' => '2026-07-10 10:00:00',
    ]);

    actingAs($user);

    put(route('calendar-events.update', $event), [
        'title' => $event->title,
        'description' => $event->description,
        'starts_at' => $event->starts_at->toDateTimeString(),
        'ends_at' => $event->ends_at->toDateTimeString(),
        'type' => $event->type->value,
        'priority' => $event->priority->value,
        'status' => CalendarEventStatus::Completed->value,
    ])->assertRedirect();

    expect($event->fresh())
        ->status->toBe(CalendarEventStatus::Completed)
        ->completed_at->not->toBeNull();
});

test('calendar events api returns only overlapping events in range', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    CalendarEvent::factory()->for($user)->create([
        'title' => 'Inside range',
        'starts_at' => '2026-07-10 09:00:00',
        'ends_at' => '2026-07-12 18:00:00',
    ]);

    CalendarEvent::factory()->for($user)->create([
        'title' => 'Before range',
        'starts_at' => '2026-06-01 09:00:00',
        'ends_at' => '2026-06-01 10:00:00',
    ]);

    CalendarEvent::factory()->for($user)->create([
        'title' => 'After range',
        'starts_at' => '2026-08-01 09:00:00',
        'ends_at' => '2026-08-01 10:00:00',
    ]);

    actingAs($user)
        ->getJson(route('internal.calendar-events.index', [
            'from' => '2026-07-01',
            'to' => '2026-07-31',
        ]))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.title', 'Inside range');
});

test('user cannot access another users calendar event', function () {
    $owner = User::factory()->create();
    $owner->assignRole('user');

    $other = User::factory()->create();
    $other->assignRole('user');

    $event = CalendarEvent::factory()->for($owner)->create();

    actingAs($other);

    put(route('calendar-events.update', $event), [
        'title' => 'Hacked',
        'description' => null,
        'starts_at' => '2026-07-10 09:00:00',
        'ends_at' => '2026-07-10 10:00:00',
        'type' => CalendarEventType::Action->value,
        'priority' => CalendarEventPriority::Standard->value,
        'status' => CalendarEventStatus::Active->value,
    ])->assertNotFound();

    delete(route('calendar-events.destroy', $event))->assertNotFound();
});

test('calendar index page renders for office user', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user)
        ->get(route('calendar.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('calendar/Index'));
});
