<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Services\ImapMailboxService;
use App\Support\Translations;
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
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $folder = $validated['folder'] ?? $account->default_folder;
        $limit = $validated['limit'] ?? 50;
        $search = isset($validated['search']) ? trim($validated['search']) : '';

        try {
            $messages = $imapMailboxService->browseRecentMessages($account, $folder, $limit, $search);
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

    public function threads(Request $request, ImapMailboxService $imapMailboxService): JsonResponse
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
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $folder = $validated['folder'] ?? $account->default_folder;
        $limit = $validated['limit'] ?? 100;
        $search = isset($validated['search']) ? trim($validated['search']) : '';

        try {
            $threads = $imapMailboxService->browseMessageThreads($account, $folder, $limit, $search);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => $threads,
            'meta' => [
                'configured' => true,
                'folder' => $folder,
            ],
        ]);
    }

    public function attachments(Request $request, ImapMailboxService $imapMailboxService): JsonResponse
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
            'uids' => ['required', 'string', 'max:1000'],
        ]);

        $folder = $validated['folder'] ?? $account->default_folder;
        $uids = array_slice(array_values(array_unique(array_filter(
            array_map('intval', explode(',', $validated['uids'])),
            fn (int $uid): bool => $uid > 0,
        ))), 0, 50);

        if ($uids === []) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'configured' => true,
                    'folder' => $folder,
                ],
            ]);
        }

        try {
            $attachmentNamesByUid = $imapMailboxService->fetchAttachmentNamesForUids($account, $folder, $uids);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $data = [];

        foreach ($attachmentNamesByUid as $uid => $names) {
            $data[(string) $uid] = $names;
        }

        return response()->json([
            'data' => $data,
            'meta' => [
                'configured' => true,
                'folder' => $folder,
            ],
        ]);
    }

    public function body(Request $request, ImapMailboxService $imapMailboxService): JsonResponse
    {
        Gate::authorize('viewAny', Project::class);

        /** @var User $user */
        $user = Auth::user();

        $account = $user->imapAccount;

        if ($account === null) {
            return response()->json([
                'message' => Translations::get('settings.email.errors.not_configured'),
            ], 422);
        }

        $validated = $request->validate([
            'folder' => ['nullable', 'string', 'max:255'],
            'uid' => ['required', 'integer', 'min:1'],
        ]);

        $folder = $validated['folder'] ?? $account->default_folder;
        $uid = (int) $validated['uid'];

        try {
            $body = $imapMailboxService->fetchMessageBody($account, $folder, $uid);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => $body,
            'meta' => [
                'configured' => true,
                'folder' => $folder,
                'uid' => $uid,
            ],
        ]);
    }

    public function folders(Request $request, ImapMailboxService $imapMailboxService): JsonResponse
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

        try {
            $folders = $imapMailboxService->listFolderTree($account);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => $folders,
            'meta' => [
                'configured' => true,
                'default_folder' => $account->default_folder,
            ],
        ]);
    }
}
