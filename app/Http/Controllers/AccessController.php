<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccessRequest;
use App\Http\Requests\UpdateAccessRequest;
use App\Models\Access;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccessController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Access::class);

        return Inertia::render('accesses/Index');
    }

    public function store(StoreAccessRequest $request): RedirectResponse
    {
        $this->authorize('create', Access::class);

        /** @var User $user */
        $user = Auth::user();

        $user->accesses()->create($request->validated());

        return to_route('accesses.index');
    }

    public function update(UpdateAccessRequest $request, Access $access): RedirectResponse
    {
        $this->authorize('update', $access);

        $access->update($request->validated());

        return to_route('accesses.index');
    }

    public function destroy(Access $access): RedirectResponse
    {
        $this->authorize('delete', $access);

        $access->delete();

        return to_route('accesses.index');
    }
}
