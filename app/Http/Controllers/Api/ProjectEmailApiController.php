<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectEmailLink;
use App\Models\User;
use App\Services\ImapMailboxService;
use App\Services\ProjectEmailService;
use App\Support\Translations;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ProjectEmailApiController extends Controller
{
    public function index(Project $project, ProjectEmailService $projectEmailService): JsonResponse
    {
        Gate::authorize('view', $project);

        /** @var User $user */
        $user = Auth::user();

        if ($user->imapAccount === null) {
            return response()->json([
                'data' => [
                    'configured' => false,
                    'links' => [],
                ],
            ]);
        }

        try {
            $links = $projectEmailService->refreshLinks($project, $user);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => [
                'configured' => true,
                'links' => $links,
            ],
        ]);
    }

    public function body(
        Project $project,
        ProjectEmailLink $emailLink,
        ImapMailboxService $imapMailboxService,
    ): JsonResponse {
        Gate::authorize('view', $project);

        abort_unless($emailLink->project_id === $project->id, 404);

        /** @var User $user */
        $user = Auth::user();

        $account = $user->imapAccount;

        abort_if($account === null, 422, Translations::get('settings.email.errors.not_configured'));

        if ($emailLink->status->value === 'missing_on_server') {
            return response()->json([
                'message' => Translations::get('projects.email.errors.missing_on_server'),
            ], 404);
        }

        try {
            $body = $imapMailboxService->fetchMessageBody(
                $account,
                $emailLink->folder,
                $emailLink->imap_uid,
            );
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => $body,
        ]);
    }
}
