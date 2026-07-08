<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasAnyRole([
            UserRole::Profiler->value,
            UserRole::Admin->value,
        ]);
    }

    public function view(User $actor, User $target): bool
    {
        return $this->canManage($actor, $target);
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(User $actor, User $target): bool
    {
        return $this->canManage($actor, $target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $this->canManage($actor, $target) && $actor->id !== $target->id;
    }

    public function manageableRole(User $actor): ?UserRole
    {
        if ($actor->hasRole(UserRole::Profiler->value)) {
            return UserRole::Admin;
        }

        if ($actor->hasRole(UserRole::Admin->value)) {
            return UserRole::User;
        }

        return null;
    }

    private function canManage(User $actor, User $target): bool
    {
        $manageableRole = $this->manageableRole($actor);

        return $manageableRole !== null
            && $target->hasRole($manageableRole->value);
    }
}
