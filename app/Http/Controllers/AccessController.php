<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyAccessRequest;
use App\Http\Requests\StoreAccessRequest;
use App\Http\Requests\UpdateAccessRequest;
use App\Models\Access;
use App\Services\AccessEncryptionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AccessController extends Controller
{
    public function index(AccessEncryptionService $encryptionService): Response
    {
        $this->authorize('viewAny', Access::class);

        return Inertia::render('accesses/Index', ['encryptionContext' => $encryptionService->context()]);
    }

    public function store(StoreAccessRequest $request, AccessEncryptionService $encryptionService): RedirectResponse
    {
        $this->authorize('create', Access::class);

        $encryptionService->create($request->validated());

        return to_route('accesses.index');
    }

    public function update(UpdateAccessRequest $request, Access $access, AccessEncryptionService $encryptionService): RedirectResponse
    {
        $this->authorize('update', $access);

        $encryptionService->update($access, $request->validated());

        return to_route('accesses.index');
    }

    public function destroy(DestroyAccessRequest $request, Access $access, AccessEncryptionService $encryptionService): RedirectResponse
    {
        $this->authorize('delete', $access);

        $encryptionService->delete($access, $request->validated('encryption_context'), $request->validated('version'));

        return to_route('accesses.index');
    }
}
