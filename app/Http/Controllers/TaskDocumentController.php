<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;

class TaskDocumentController extends Controller
{
    public function store(Task $task, Document $document): RedirectResponse
    {
        $this->authorize('update', $task);
        $this->authorize('view', $document);

        abort_unless($document->user_id === $task->user_id, 404);

        $task->documents()->syncWithoutDetaching([$document->id]);

        return back();
    }

    public function destroy(Task $task, Document $document): RedirectResponse
    {
        $this->authorize('update', $task);
        $this->authorize('view', $document);

        abort_unless($document->user_id === $task->user_id, 404);

        $task->documents()->detach($document->id);

        return back();
    }
}
