<?php

namespace App\Http\Controllers;

use App\Enums\ProjectEmailLinkStatus;
use App\Http\Requests\StoreProjectEmailLinkRequest;
use App\Models\Project;
use App\Models\ProjectEmailLink;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ProjectEmailLinkController extends Controller
{
    public function store(StoreProjectEmailLinkRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        /** @var User $user */
        $user = Auth::user();

        abort_if($user->imapAccount === null, 422);

        $project->emailLinks()->updateOrCreate(
            [
                'folder' => $request->validated('folder'),
                'imap_uid' => $request->validated('imap_uid'),
            ],
            [
                'user_id' => $user->id,
                'uidvalidity' => $request->validated('uidvalidity'),
                'subject' => $request->validated('subject'),
                'from_name' => $request->validated('from_name'),
                'from_address' => $request->validated('from_address'),
                'sent_at' => $request->validated('sent_at'),
                'status' => ProjectEmailLinkStatus::Active,
                'last_verified_at' => now(),
            ],
        );

        return back();
    }

    public function destroy(Project $project, ProjectEmailLink $emailLink): RedirectResponse
    {
        $this->authorize('update', $project);

        abort_unless($emailLink->project_id === $project->id, 404);

        $emailLink->delete();

        return back();
    }
}
