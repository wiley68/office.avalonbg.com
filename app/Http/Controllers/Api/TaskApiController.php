<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\TaskTreeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TaskApiController extends Controller
{
    public function index(Project $project, TaskTreeService $taskTreeService): JsonResponse
    {
        Gate::authorize('view', $project);

        return response()->json([
            'data' => $taskTreeService->nestedTreeForProject($project)->values(),
        ]);
    }
}
