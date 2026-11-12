<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\RotateAccessEncryptionKeyRequest;
use App\Services\AccessEncryptionService;
use Illuminate\Http\RedirectResponse;

class AccessEncryptionKeyController extends Controller
{
    public function rotate(RotateAccessEncryptionKeyRequest $request, AccessEncryptionService $encryptionService): RedirectResponse
    {
        $encryptionService->rotateKey($request->validated('encryption_context'));

        return back();
    }
}
