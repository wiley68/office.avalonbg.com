<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ProfitEntry;
use App\Models\User;

class ProfitEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isOfficeUser($user);
    }

    public function view(User $user, ProfitEntry $profitEntry): bool
    {
        return $this->ownsEntry($user, $profitEntry);
    }

    public function create(User $user): bool
    {
        return $this->isOfficeUser($user);
    }

    public function update(User $user, ProfitEntry $profitEntry): bool
    {
        return $this->ownsEntry($user, $profitEntry);
    }

    public function delete(User $user, ProfitEntry $profitEntry): bool
    {
        return $this->ownsEntry($user, $profitEntry);
    }

    private function isOfficeUser(User $user): bool
    {
        return $user->hasRole(UserRole::User->value);
    }

    private function ownsEntry(User $user, ProfitEntry $profitEntry): bool
    {
        return $this->isOfficeUser($user) && $profitEntry->user_id === $user->id;
    }
}
