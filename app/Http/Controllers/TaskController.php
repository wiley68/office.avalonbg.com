<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\ReorderTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskTreeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function store(
        StoreTaskRequest $request,
        Project $project,
        TaskTreeService $taskTreeService,
    ): RedirectResponse {
        $this->authorize('create', [Task::class, $project]);

        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validated();
        $data = $taskTreeService->applyTaskStatusAttributes($validated);

        if ($data['status'] === TaskStatus::Completed->value) {
            $taskTreeService->assertCanComplete(new Task([
                'parent_id' => $validated['parent_id'] ?? null,
            ]));
        }

        $project->tasks()->create([
            ...$data,
            'user_id' => $user->id,
            'sort_order' => $taskTreeService->nextSortOrder(
                $project,
                $validated['parent_id'] ?? null,
            ),
        ]);

        return back();
    }

    public function update(
        UpdateTaskRequest $request,
        Task $task,
        TaskTreeService $taskTreeService,
    ): RedirectResponse {
        $this->authorize('update', $task);

        $validated = $request->validated();
        $data = $taskTreeService->applyTaskStatusAttributes($validated, $task);

        if ($data['status'] === TaskStatus::Completed->value && $task->status !== TaskStatus::Completed) {
            $task->fill($validated);
            $task->load('parent');
            $taskTreeService->assertCanComplete($task);
        }

        $task->update($data);

        return back();
    }

    public function complete(Task $task, TaskTreeService $taskTreeService): RedirectResponse
    {
        $this->authorize('complete', $task);

        $task->load('parent');
        $taskTreeService->assertCanComplete($task);

        $task->update([
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
        ]);

        return back();
    }

    public function reorder(
        ReorderTaskRequest $request,
        Task $task,
        TaskTreeService $taskTreeService,
    ): RedirectResponse {
        $this->authorize('reorder', $task);

        $taskTreeService->reorder(
            $task,
            $request->validated('parent_id'),
            $request->integer('sort_order'),
        );

        return back();
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return back();
    }
}
