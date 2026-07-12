<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isOfficeUser($user);
    }

    public function view(User $user, Document $document): bool
    {
        return $this->ownsDocument($user, $document);
    }

    public function create(User $user): bool
    {
        return $this->isOfficeUser($user);
    }

    public function update(User $user, Document $document): bool
    {
        return $this->ownsDocument($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->ownsDocument($user, $document);
    }

    public function download(User $user, Document $document): bool
    {
        return $this->ownsDocument($user, $document);
    }

    private function isOfficeUser(User $user): bool
    {
        return $user->hasRole(UserRole::User->value);
    }

    private function ownsDocument(User $user, Document $document): bool
    {
        return $this->isOfficeUser($user) && $document->user_id === $user->id;
    }
}
