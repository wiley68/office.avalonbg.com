<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Access;
use App\Models\User;

class AccessPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isOfficeUser($user);
    }

    public function view(User $user, Access $access): bool
    {
        return $this->ownsAccess($user, $access);
    }

    public function create(User $user): bool
    {
        return $this->isOfficeUser($user);
    }

    public function update(User $user, Access $access): bool
    {
        return $this->ownsAccess($user, $access);
    }

    public function delete(User $user, Access $access): bool
    {
        return $this->ownsAccess($user, $access);
    }

    public function transformData(User $user): bool
    {
        return $this->isOfficeUser($user);
    }

    public function manageEncryptionKey(User $user): bool
    {
        return $this->isOfficeUser($user);
    }

    private function isOfficeUser(User $user): bool
    {
        return $user->hasRole(UserRole::User->value);
    }

    private function ownsAccess(User $user, Access $access): bool
    {
        return $this->isOfficeUser($user) && $access->user_id === $user->id;
    }
}
