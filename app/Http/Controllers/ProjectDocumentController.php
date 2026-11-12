<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;

class ProjectDocumentController extends Controller
{
    public function store(Project $project, Document $document): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->authorize('view', $document);

        abort_unless($document->group_id !== null && $document->group_id === $project->group_id, 404);

        $project->documents()->syncWithoutDetaching([$document->id]);

        return back();
    }

    public function destroy(Project $project, Document $document): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->authorize('view', $document);

        abort_unless($document->group_id !== null && $document->group_id === $project->group_id, 404);

        $project->documents()->detach($document->id);

        return back();
    }
}
