<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyProjectEmailThreadRequest;
use App\Http\Requests\StoreBatchProjectEmailLinksRequest;
use App\Http\Requests\StoreProjectEmailLinkRequest;
use App\Models\Project;
use App\Models\ProjectEmailLink;
use App\Models\User;
use App\Services\ProjectEmailArchiveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProjectEmailLinkController extends Controller
{
    public function link(Project $project): Response|RedirectResponse
    {
        $this->authorize('update', $project);

        /** @var User $user */
        $user = Auth::user();

        if ($user->imapAccount === null) {
            return to_route('projects.show', [
                'project' => $project,
                'tab' => 'email',
            ]);
        }

        return Inertia::render('projects/email/Link', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
            'defaultFolder' => $user->imapAccount->default_folder,
        ]);
    }

    public function store(
        StoreProjectEmailLinkRequest $request,
        Project $project,
        ProjectEmailArchiveService $projectEmailArchiveService,
    ): RedirectResponse {
        $this->authorize('update', $project);

        /** @var User $user */
        $user = Auth::user();

        abort_if($user->imapAccount === null, 422);

        $projectEmailArchiveService->archiveMessages($user, $project, [
            $request->validated(),
        ]);

        return back();
    }

    public function storeBatch(
        StoreBatchProjectEmailLinksRequest $request,
        Project $project,
        ProjectEmailArchiveService $projectEmailArchiveService,
    ): RedirectResponse {
        $this->authorize('update', $project);

        /** @var User $user */
        $user = Auth::user();

        abort_if($user->imapAccount === null, 422);

        $projectEmailArchiveService->archiveMessages(
            $user,
            $project,
            $request->validated('messages'),
        );

        return to_route('projects.show', [
            'project' => $project,
            'tab' => 'email',
        ]);
    }

    public function destroy(Project $project, ProjectEmailLink $emailLink): RedirectResponse
    {
        $this->authorize('update', $project);

        abort_unless($emailLink->project_id === $project->id, 404);

        $emailLink->delete();

        return back();
    }

    public function destroyThread(
        DestroyProjectEmailThreadRequest $request,
        Project $project,
    ): RedirectResponse {
        $this->authorize('update', $project);

        $project->emailLinks()
            ->whereIn('id', $request->validated('link_ids'))
            ->delete();

        return back();
    }
}
