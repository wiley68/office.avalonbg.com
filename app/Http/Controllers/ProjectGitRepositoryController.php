<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertProjectGitRepositoryRequest;
use App\Models\Project;
use App\Services\GitHubRepositoryParser;
use Illuminate\Http\RedirectResponse;

class ProjectGitRepositoryController extends Controller
{
    public function upsert(
        UpsertProjectGitRepositoryRequest $request,
        Project $project,
        GitHubRepositoryParser $repositoryParser,
    ): RedirectResponse {
        $this->authorize('update', $project);

        $parsed = $repositoryParser->parse($request->validated('repository'));

        $attributes = [
            'owner' => $parsed['owner'],
            'repo' => $parsed['repo'],
            'default_branch' => $request->validated('default_branch') ?: 'main',
        ];

        if ($request->filled('access_token')) {
            $attributes['access_token'] = $request->validated('access_token');
        }

        $project->gitRepository()->updateOrCreate(
            ['project_id' => $project->id],
            $attributes,
        );

        return back();
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project->gitRepository()?->delete();

        return back();
    }
}
