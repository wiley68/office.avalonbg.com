<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransformAccessDataRequest;
use App\Models\User;
use App\Services\AccessEncryptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use RuntimeException;

class AccessDataTransformController extends Controller
{
    public function __invoke(
        TransformAccessDataRequest $request,
        AccessEncryptionService $encryptionService,
    ): JsonResponse {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validated();

        try {
            $userKey = $encryptionService->requireKey($user);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        try {
            if ($validated['action'] === 'encrypt') {
                return response()->json([
                    'content' => $encryptionService->encryptData($validated['content'], $userKey),
                    'is_encrypted' => true,
                ]);
            }

            if (! $validated['is_encrypted']) {
                return response()->json([
                    'message' => __('accesses.errors.not_encrypted'),
                ], 422);
            }

            return response()->json([
                'content' => $encryptionService->decryptData($validated['content'], $userKey),
                'is_encrypted' => false,
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
