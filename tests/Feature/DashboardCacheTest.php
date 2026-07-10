<?php

use App\Models\User;
use App\Support\DashboardCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('profiler', 'web');
    Role::findOrCreate('admin', 'web');
});

test('authenticated user can clear dashboard cache', function () {
    $profiler = User::factory()->create();
    $profiler->assignRole('profiler');

    $cacheKey = DashboardCache::key('admin_user_count', $profiler->id);
    Cache::put($cacheKey, 99, now()->addDay());

    actingAs($profiler)
        ->from(route('dashboard'))
        ->post(route('dashboard.clear-cache'))
        ->assertRedirect(route('dashboard'));

    expect(Cache::has($cacheKey))->toBeFalse();
});

test('guest cannot clear dashboard cache', function () {
    post(route('dashboard.clear-cache'))
        ->assertRedirect(route('login'));
});
