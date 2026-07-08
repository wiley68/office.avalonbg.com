<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        /** @var User $actor */
        $actor = Auth::user();
        $manageableRole = app(UserPolicy::class)->manageableRole($actor);

        return Inertia::render('users/Index', [
            'users' => User::query()
                ->role($manageableRole?->value)
                ->select(['id', 'name', 'email', 'created_at'])
                ->latest()
                ->get(),
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
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = Auth::user();
        $manageableRole = app(UserPolicy::class)->manageableRole($actor);

        $user = User::query()->create($request->validated());
        $user->syncRoles([$manageableRole?->value]);
        $user->sendEmailVerificationNotification();

        return to_route('users.index');
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        /** @var User $actor */
        $actor = Auth::user();

        return Inertia::render('users/Edit', [
            'user' => $user->only(['id', 'name', 'email']),
            'manageableRole' => app(UserPolicy::class)->manageableRole($actor)?->value,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);

        return to_route('users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return to_route('users.index');
    }
}
