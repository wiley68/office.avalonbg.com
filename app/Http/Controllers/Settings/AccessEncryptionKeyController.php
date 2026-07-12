<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\RotateAccessEncryptionKeyRequest;
use App\Http\Requests\Settings\UpdateAccessEncryptionKeyRequest;
use App\Models\Access;
use App\Services\AccessEncryptionService;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class AccessEncryptionKeyController extends Controller
{
    public function store(
        UpdateAccessEncryptionKeyRequest $request,
        AccessEncryptionService $encryptionService,
    ): RedirectResponse {
        $this->authorize('manageEncryptionKey', Access::class);

        $user = $request->user();

        if ($encryptionService->hasStoredKey($user)) {
            return back()->withErrors([
                'encryption_key' => __('accesses.encryption_key.already_set'),
            ]);
        }

        $encryptionService->storeKey($user, $request->validated('encryption_key'));

        return back();
    }

    public function rotate(
        RotateAccessEncryptionKeyRequest $request,
        AccessEncryptionService $encryptionService,
    ): RedirectResponse {
        $this->authorize('manageEncryptionKey', Access::class);

        $user = $request->user();

        if (! $encryptionService->hasStoredKey($user)) {
            return back()->withErrors([
                'encryption_key' => __('accesses.encryption_key.not_set'),
            ]);
        }

        try {
            $encryptionService->rotateKey($user, $request->validated('encryption_key'));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors([
                'encryption_key' => $exception->getMessage(),
            ]);
        }

        return back();
    }
}
