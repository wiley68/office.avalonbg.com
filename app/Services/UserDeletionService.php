<?php

namespace App\Services;

use App\Models\ProjectEmailLink;
use App\Models\Task;
use App\Models\User;
use App\Support\Translations;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class UserDeletionService
{
    public function delete(User $user, string $errorField = 'user'): void
    {
        try {
            $user->getConnection()->transaction(function () use ($user, $errorField): void {
                foreach (['projects', 'documents', 'calendarEvents', 'accesses', 'profitEntries'] as $relation) {
                    if ($user->{$relation}()->exists()) {
                        throw ValidationException::withMessages([$errorField => Translations::get('groups.creator_delete_restricted')]);
                    }
                }

                if (Task::query()->where('user_id', $user->id)->exists()
                    || ProjectEmailLink::query()->where('user_id', $user->id)->exists()) {
                    throw ValidationException::withMessages([$errorField => Translations::get('groups.creator_delete_restricted')]);
                }

                $user->delete();
            });
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }

            throw ValidationException::withMessages([$errorField => Translations::get('groups.creator_delete_restricted')]);
        }
    }
}
