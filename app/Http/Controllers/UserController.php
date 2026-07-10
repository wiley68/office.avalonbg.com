<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\EncryptedUsersExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
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
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        /** @var User $actor */
        $actor = Auth::user();
        $manageableRole = app(UserPolicy::class)->manageableRole($actor);

        $user = User::query()->create([
            ...$request->validated(),
            'must_change_password' => true,
        ]);
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
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
            ],
            'manageableRole' => app(UserPolicy::class)->manageableRole($actor)?->value,
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

        $user->update($validated);

        return to_route('users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return to_route('users.index');
    }

    public function export(ExportPasswordRequest $request, EncryptedUsersExporter $exporter): BinaryFileResponse
    {
        $this->authorize('viewAny', User::class);

        /** @var User $actor */
        $actor = Auth::user();

        return $exporter->download($actor, $request->validated('password'));
    }
}
