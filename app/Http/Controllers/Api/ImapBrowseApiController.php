<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Services\ImapMailboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ImapBrowseApiController extends Controller
{
    public function index(Request $request, ImapMailboxService $imapMailboxService): JsonResponse
    {
        Gate::authorize('viewAny', Project::class);

        /** @var User $user */
        $user = Auth::user();

        $account = $user->imapAccount;

        if ($account === null) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'configured' => false,
                ],
            ]);
        }

        $validated = $request->validate([
            'folder' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $folder = $validated['folder'] ?? $account->default_folder;
        $limit = $validated['limit'] ?? 50;

        try {
            $messages = $imapMailboxService->browseRecentMessages($account, $folder, $limit);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => $messages,
            'meta' => [
                'configured' => true,
                'folder' => $folder,
            ],
        ]);
    }
}
