<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Profiler->value);
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->hasRole(UserRole::Profiler->value);
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return $user->hasRole(UserRole::Profiler->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole(UserRole::Profiler->value);
    }
}
