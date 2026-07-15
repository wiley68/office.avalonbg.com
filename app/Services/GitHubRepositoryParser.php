<?php

namespace App\Services;

use App\Support\Translations;
use Illuminate\Validation\ValidationException;

class GitHubRepositoryParser
{
    private const ALLOWED_HOSTS = ['github.com', 'www.github.com'];

    /**
     * @return array{owner: string, repo: string}
     */
    public function parse(string $input): array
    {
        $input = trim($input);

        if ($input === '') {
            throw ValidationException::withMessages([
                'repository' => Translations::get('projects.git.errors.invalid_repository'),
            ]);
        }

        if (! str_contains($input, '://') && ! str_contains($input, 'github.com')) {
            return $this->parseOwnerRepo($input);
        }

        $host = parse_url($input, PHP_URL_HOST);
        $path = parse_url($input, PHP_URL_PATH);

        if (! is_string($host) || ! in_array(strtolower($host), self::ALLOWED_HOSTS, true)) {
            throw ValidationException::withMessages([
                'repository' => Translations::get('projects.git.errors.invalid_repository'),
            ]);
        }

        if (! is_string($path) || $path === '' || $path === '/') {
            throw ValidationException::withMessages([
                'repository' => Translations::get('projects.git.errors.invalid_repository'),
            ]);
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        if (count($segments) < 2) {
            throw ValidationException::withMessages([
                'repository' => Translations::get('projects.git.errors.invalid_repository'),
            ]);
        }

        return $this->parseOwnerRepo($segments[0].'/'.$segments[1]);
    }

    /**
     * @return array{owner: string, repo: string}
     */
    private function parseOwnerRepo(string $input): array
    {
        $input = trim($input, '/');
        $input = preg_replace('/\.git$/', '', $input) ?? $input;

        if (! preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $input)) {
            throw ValidationException::withMessages([
                'repository' => Translations::get('projects.git.errors.invalid_repository'),
            ]);
        }

        [$owner, $repo] = explode('/', $input, 2);

        return [
            'owner' => $owner,
            'repo' => $repo,
        ];
    }
}
