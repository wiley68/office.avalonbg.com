<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ProfitType;
use App\Models\User;

class ProfitTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isOfficeUser($user);
    }

    public function view(User $user, ProfitType $profitType): bool
    {
        return $this->isOfficeUser($user) && $profitType->group_id === $user->group_id;
    }

    public function create(User $user): bool
    {
        return $this->isOfficeUser($user);
    }

    public function update(User $user, ProfitType $profitType): bool
    {
        return $this->view($user, $profitType);
    }

    public function delete(User $user, ProfitType $profitType): bool
    {
        return $this->view($user, $profitType) && ! $profitType->entries()->exists();
    }

    private function isOfficeUser(User $user): bool
    {
        return $user->hasRole(UserRole::User->value)
            && $user->group_id !== null
            && $user->group()->exists();
    }
}
