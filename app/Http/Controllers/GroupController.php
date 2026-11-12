<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveGroupRequest;
use App\Models\Group;
use App\Models\ProjectEmailAttachment;
use App\Support\Translations;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class GroupController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Group::class);

        return Inertia::render('groups/Index', [
            'groups' => Group::query()->select(['id', 'name'])->withCount('users')->orderBy('name')->get(),
        ]);
    }

    public function store(SaveGroupRequest $request): RedirectResponse
    {
        try {
            Group::getConnectionResolver()->connection()->transaction(function () use ($request): void {
                $group = Group::query()->create($request->safe()->only('name'));
                $key = base64_decode(Crypt::decryptString($group->access_encryption_key), true);

                if (! is_string($key) || strlen($key) !== 32) {
                    throw ValidationException::withMessages(['name' => Translations::get('groups.key_unavailable')]);
                }
            });
        } catch (QueryException $exception) {
            throw ValidationException::withMessages(['name' => Translations::get('groups.create_failed')]);
        } catch (RuntimeException|DecryptException $exception) {
            throw ValidationException::withMessages(['name' => Translations::get('groups.key_unavailable')]);
        }

        return to_route('groups.index');
    }

    public function update(SaveGroupRequest $request, Group $group): RedirectResponse
    {
        $group->update($request->safe()->only('name'));

        return to_route('groups.index');
    }

    public function destroy(Group $group): RedirectResponse
    {
        $this->authorize('delete', $group);

        try {
            $group->getConnection()->transaction(function () use ($group): void {
                $group = Group::query()->lockForUpdate()->findOrFail($group->id);

                foreach (['users', 'projects', 'tasks', 'documents', 'calendarEvents', 'accesses', 'profitEntries', 'profitTypes', 'projectEmailLinks'] as $relation) {
                    if ($group->{$relation}()->exists()) {
                        throw ValidationException::withMessages(['group' => Translations::get('groups.delete_restricted')]);
                    }
                }

                if (ProjectEmailAttachment::query()->where('group_id', $group->id)->exists()) {
                    throw ValidationException::withMessages(['group' => Translations::get('groups.delete_restricted')]);
                }

                $group->delete();
            });
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }

            throw ValidationException::withMessages(['group' => Translations::get('groups.delete_restricted')]);
        }

        return to_route('groups.index');
    }
}
