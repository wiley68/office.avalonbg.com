<?php

namespace App\Services;

use App\Models\ProjectGitRepository;
use App\Support\Translations;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class GitHubApiService
{
    /**
     * @return array<string, mixed>
     */
    public function repository(ProjectGitRepository $gitRepository): array
    {
        $response = $this->client($gitRepository)
            ->get($this->repositoryUrl($gitRepository->owner, $gitRepository->repo));

        $this->assertSuccessful($response, 'repository');

        return $response->json();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function commits(
        ProjectGitRepository $gitRepository,
        int $page = 1,
        int $perPage = 30,
    ): array {
        $response = $this->client($gitRepository)
            ->get($this->commitsUrl($gitRepository), [
                'sha' => $gitRepository->default_branch,
                'per_page' => min(max($perPage, 1), 100),
                'page' => max($page, 1),
            ]);

        $this->assertSuccessful($response, 'commits');

        /** @var list<array<string, mixed>> $commits */
        $commits = $response->json();

        return $commits;
    }

    /**
     * @param  array<string, mixed>  $repository
     * @param  list<array<string, mixed>>  $commits
     * @return array<string, mixed>
     */
    public function buildOverview(
        ProjectGitRepository $gitRepository,
        array $repository,
        array $commits,
        int $page,
        int $perPage,
    ): array {
        $latestCommit = $commits[0] ?? null;

        return [
            'repository' => [
                'owner' => $gitRepository->owner,
                'repo' => $gitRepository->repo,
                'default_branch' => $gitRepository->default_branch,
                'html_url' => $gitRepository->repositoryUrl(),
                'has_access_token' => filled($gitRepository->access_token),
            ],
            'stats' => [
                'default_branch' => $repository['default_branch'] ?? $gitRepository->default_branch,
                'open_issues_count' => $repository['open_issues_count'] ?? 0,
                'stargazers_count' => $repository['stargazers_count'] ?? 0,
                'forks_count' => $repository['forks_count'] ?? 0,
                'pushed_at' => $repository['pushed_at'] ?? null,
                'last_commit_at' => data_get($latestCommit, 'commit.author.date'),
                'last_commit_message' => data_get($latestCommit, 'commit.message'),
            ],
            'commits' => [
                'data' => array_map(
                    fn (array $commit): array => $this->transformCommit($commit),
                    $commits,
                ),
                'meta' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'has_more' => count($commits) === $perPage,
                ],
            ],
        ];
    }

    private function client(ProjectGitRepository $gitRepository): PendingRequest
    {
        $token = $gitRepository->access_token ?: config('services.github.token');

        $client = Http::baseUrl('https://api.github.com')
            ->accept('application/vnd.github+json')
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => config('app.name', 'Avalon Office'),
            ])
            ->timeout(15);

        if (filled($token)) {
            $client = $client->withToken($token);
        }

        return $client;
    }

    private function repositoryUrl(string $owner, string $repo): string
    {
        return '/repos/'.$owner.'/'.$repo;
    }

    private function commitsUrl(ProjectGitRepository $gitRepository): string
    {
        return '/repos/'.$gitRepository->owner.'/'.$gitRepository->repo.'/commits';
    }

    /**
     * @param  array<string, mixed>  $commit
     * @return array<string, mixed>
     */
    private function transformCommit(array $commit): array
    {
        return [
            'sha' => $commit['sha'] ?? '',
            'short_sha' => substr((string) ($commit['sha'] ?? ''), 0, 7),
            'message' => data_get($commit, 'commit.message'),
            'author_name' => data_get($commit, 'commit.author.name'),
            'committed_at' => data_get($commit, 'commit.author.date'),
            'html_url' => $commit['html_url'] ?? null,
        ];
    }

    /**
     * @param  Response  $response
     */
    private function assertSuccessful($response, string $context): void
    {
        try {
            $response->throw();
        } catch (RequestException $exception) {
            $status = $exception->response?->status();

            $message = match ($status) {
                404 => Translations::get('projects.git.errors.repository_not_found'),
                403 => Translations::get('projects.git.errors.rate_limited'),
                default => Translations::get('projects.git.errors.fetch_failed'),
            };

            throw ValidationException::withMessages([
                $context => $message,
            ]);
        }
    }
}
