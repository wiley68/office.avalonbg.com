<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\CalendarEvent;
use App\Models\User;

class CalendarEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isOfficeUser($user);
    }

    public function view(User $user, CalendarEvent $calendarEvent): bool
    {
        return $this->ownsCalendarEvent($user, $calendarEvent);
    }

    public function create(User $user): bool
    {
        return $this->isOfficeUser($user);
    }

    public function update(User $user, CalendarEvent $calendarEvent): bool
    {
        return $this->ownsCalendarEvent($user, $calendarEvent);
    }

    public function delete(User $user, CalendarEvent $calendarEvent): bool
    {
        return $this->ownsCalendarEvent($user, $calendarEvent);
    }

    private function isOfficeUser(User $user): bool
    {
        return $user->hasRole(UserRole::User->value)
            && $user->group_id !== null
            && $user->group()->exists();
    }

    private function ownsCalendarEvent(User $user, CalendarEvent $calendarEvent): bool
    {
        return $this->isOfficeUser($user) && $calendarEvent->group_id === $user->group_id;
    }
}
