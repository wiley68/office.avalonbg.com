<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isOfficeUser($user);
    }

    public function view(User $user, Project $project): bool
    {
        return $this->ownsProject($user, $project);
    }

    public function create(User $user): bool
    {
        return $this->isOfficeUser($user);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->ownsProject($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->ownsProject($user, $project);
    }

    private function isOfficeUser(User $user): bool
    {
        return $user->hasRole(UserRole::User->value);
    }

    private function ownsProject(User $user, Project $project): bool
    {
        return $this->isOfficeUser($user) && $project->user_id === $user->id;
    }
}
