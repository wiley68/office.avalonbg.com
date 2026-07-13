<?php

namespace App\Http\Middleware;

use App\Enums\Appearance;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\AccessEncryptionService;
use App\Support\DashboardCache;
use App\Support\Translations;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'organization' => (string) config('app.organization'),
            'email' => (string) config('app.contact_email'),
            'shopUrl' => (string) config('app.shop_url'),
            'version' => (string) config('app.version'),
            'documentIconsVersion' => (string) config('documents.icons_version'),
            'locale' => app()->getLocale(),
            'locales' => [
                ['code' => 'en', 'label' => 'English'],
                ['code' => 'bg', 'label' => 'Български'],
            ],
            'translations' => Translations::forLocale(),
            'appearance' => $this->resolveAppearance($request),
            'auth' => [
                'user' => $request->user()
                    ? [
                        ...$request->user()->only(['id', 'name', 'email', 'email_verified_at', 'must_change_password', 'created_at', 'updated_at']),
                        'role' => $request->user()->primaryRole()?->value,
                        'role_label' => $request->user()->primaryRole()?->getLabel(),
                        'is_profiler' => $request->user()->isProfiler(),
                        'is_admin' => $request->user()->isAdmin(),
                        'can_manage_users' => $request->user()->canManageUsers(),
                        'has_office_access' => $request->user()->hasOfficeAccess(),
                        'is_office_user' => $request->user()->isOfficeUser(),
                        'has_access_encryption_key' => app(AccessEncryptionService::class)->hasStoredKey($request->user()),
                    ]
                    : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'admin_user_count' => function () use ($request) {
                $user = $request->user();

                if ($user === null || ! $user->hasRole(UserRole::Profiler->value)) {
                    return null;
                }

                return DashboardCache::remember(
                    'admin_user_count',
                    $user->id,
                    fn () => User::role(UserRole::Admin->value)->count(),
                );
            },
            'office_user_count' => function () use ($request) {
                $user = $request->user();

                if ($user === null || ! $user->hasRole(UserRole::Admin->value)) {
                    return null;
                }

                return DashboardCache::remember(
                    'office_user_count',
                    $user->id,
                    fn () => User::role(UserRole::User->value)->count(),
                );
            },
        ];
    }

    /**
     * @return value-of<Appearance>
     */
    private function resolveAppearance(Request $request): string
    {
        $user = $request->user();

        if ($user !== null) {
            return $user->appearance ?? Appearance::System->value;
        }

        $cookieAppearance = $request->cookie('appearance');

        if (Appearance::tryFrom((string) $cookieAppearance) !== null) {
            return (string) $cookieAppearance;
        }

        return Appearance::System->value;
    }
}
