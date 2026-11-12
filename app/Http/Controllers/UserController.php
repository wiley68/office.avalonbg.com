<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Group;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\EncryptedUsersExporter;
use App\Services\UserDeletionService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        /** @var User $actor */
        $actor = Auth::user();
        $manageableRole = app(UserPolicy::class)->manageableRole($actor);

        return Inertia::render('users/Index', [
            'manageableRole' => $manageableRole?->value,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        /** @var User $actor */
        $actor = Auth::user();

        return Inertia::render('users/Create', [
            'manageableRole' => app(UserPolicy::class)->manageableRole($actor)?->value,
            'groups' => $actor->isProfiler() ? [] : Group::query()->select(['id', 'name'])->orderBy('name')->get(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        /** @var User $actor */
        $actor = Auth::user();
        $manageableRole = app(UserPolicy::class)->manageableRole($actor);

        $user = User::getConnectionResolver()->connection()->transaction(function () use ($request, $manageableRole): User {
            $user = new User([
                ...$request->safe()->except('group_id'),
                'must_change_password' => true,
            ]);
            $user->group()->associate($this->assignmentGroup($request->validated('group_id')));
            $user->save();
            $user->syncRoles([$manageableRole->value]);

            return $user;
        });
        $user->sendEmailVerificationNotification();

        return to_route('users.index');
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        /** @var User $actor */
        $actor = Auth::user();

        return Inertia::render('users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'group_id' => $user->group_id,
                'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
            ],
            'manageableRole' => app(UserPolicy::class)->manageableRole($actor)?->value,
            'groups' => $actor->isProfiler() ? [] : Group::query()->select(['id', 'name'])->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validated();

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['must_change_password'] = true;
        }

        $user->getConnection()->transaction(function () use ($user, $validated): void {
            $groupId = $validated['group_id'] ?? null;
            unset($validated['group_id']);

            $user->fill($validated);
            $user->group()->associate($this->assignmentGroup($groupId));
            $user->save();
        });

        return to_route('users.edit', $user);
    }

    public function destroy(User $user, UserDeletionService $deletionService): RedirectResponse
    {
        $this->authorize('delete', $user);

        $deletionService->delete($user);

        return to_route('users.index');
    }

    public function export(ExportPasswordRequest $request, EncryptedUsersExporter $exporter): BinaryFileResponse
    {
        $this->authorize('viewAny', User::class);

        /** @var User $actor */
        $actor = Auth::user();

        return $exporter->download($actor, $request->validated('password'));
    }

    private function assignmentGroup(int|string|null $groupId): ?Group
    {
        if ($groupId === null) {
            return null;
        }

        $group = Group::query()->select('id')->lockForUpdate()->find($groupId);

        if ($group === null) {
            throw ValidationException::withMessages(['group_id' => Translations::get('groups.assignment_invalid')]);
        }

        return $group;
    }
}
