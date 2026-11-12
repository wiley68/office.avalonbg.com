<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransformAccessDataRequest;
use App\Models\Access;
use App\Services\AccessEncryptionService;
use App\Support\Translations;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AccessDataTransformController extends Controller
{
    public function __invoke(TransformAccessDataRequest $request, AccessEncryptionService $encryptionService): JsonResponse
    {
        $validated = $request->validated();
        $access = isset($validated['access_id']) ? Access::query()->where('group_id', $request->user()->group_id)->findOrFail($validated['access_id']) : null;
        $context = $validated['encryption_context'];
        $version = $validated['version'] ?? null;

        if ($validated['action'] === 'encrypt') {
            if ($validated['is_encrypted']) {
                throw ValidationException::withMessages(['is_encrypted' => Translations::get('accesses.errors.already_encrypted')]);
            }

            $content = $encryptionService->encryptData($validated['content'], $context, $access, $version);
        } else {
            if (! $validated['is_encrypted']) {
                throw ValidationException::withMessages(['content' => Translations::get('accesses.errors.not_encrypted')]);
            }

            $content = $encryptionService->decryptData($validated['content'], $context, $access, $version);
        }

        return response()->json([
            'content' => $content,
            'is_encrypted' => $validated['action'] === 'encrypt',
            'encryption_context' => $context,
        ]);
    }
}
