<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $this->ownsProject($user, $project);
    }

    public function view(User $user, Task $task): bool
    {
        return $this->ownsTask($user, $task);
    }

    public function create(User $user, Project $project): bool
    {
        return $this->ownsProject($user, $project);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->ownsTask($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->ownsTask($user, $task);
    }

    public function complete(User $user, Task $task): bool
    {
        return $this->ownsTask($user, $task);
    }

    public function reorder(User $user, Task $task): bool
    {
        return $this->ownsTask($user, $task);
    }

    private function isOfficeUser(User $user): bool
    {
        return $user->hasRole(UserRole::User->value);
    }

    private function ownsProject(User $user, Project $project): bool
    {
        return $this->isOfficeUser($user) && $project->user_id === $user->id;
    }

    private function ownsTask(User $user, Task $task): bool
    {
        return $this->isOfficeUser($user) && $task->user_id === $user->id;
    }
}
