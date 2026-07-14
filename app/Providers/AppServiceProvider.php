<?php

namespace App\Providers;

use App\Ai\Storage\ContextualDatabaseConversationStore;
use App\Models\Access;
use App\Models\User;
use App\Support\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Fortify;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use App\Http\Middleware\ForceHttps;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ConversationStore::class, ContextualDatabaseConversationStore::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureUrlForReverseProxy();
        $this->configureHttpsOnly();
        $this->configureAuditLogging();
        $this->configureAccessGates();
    }

    protected function configureAccessGates(): void
    {
        Gate::define('manage-accesses', fn (User $user): bool => $user->can('viewAny', Access::class));
        Gate::define('manage-access-encryption-key', fn (User $user): bool => $user->can('manageEncryptionKey', Access::class));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn (): Password => Password::min(9)
                ->mixedCase()
                ->numbers()
                ->symbols(),
        );
    }

    /**
     * Behind Cloudflare / nginx / tunnel: клиентът е по HTTPS, а APP_URL може да е http://localhost.
     * Генерираните URL-и и схемата да следват реалния протокол.
     */
    protected function configureUrlForReverseProxy(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $appUrl = config('app.url');
        if (is_string($appUrl) && str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');

            return;
        }

        if (request()->header('X-Forwarded-Proto') === 'https') {
            URL::forceScheme('https');
        }
    }

    /**
     * Redirect all non-HTTPS requests to HTTPS and force URL generation.
     *
     * Enable via env('APP_FORCE_HTTPS', false).
     */
    protected function configureHttpsOnly(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $forceHttps = filter_var(env('APP_FORCE_HTTPS', false), FILTER_VALIDATE_BOOLEAN);
        if (! $forceHttps) {
            return;
        }

        $kernel = $this->app->make(Kernel::class);
        if (! $kernel instanceof HttpKernel) {
            return;
        }

        $kernel->pushMiddleware(ForceHttps::class);
    }

    protected function configureAuditLogging(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if ($event->guard !== 'web' || ! $event->user instanceof User) {
                return;
            }

            AuditLogger::logLoginSuccess($event->user);
        });

        Event::listen(Failed::class, function (Failed $event): void {
            if ($event->guard !== 'web') {
                return;
            }

            $email = (string) ($event->credentials[Fortify::username()] ?? $event->credentials['email'] ?? '—');
            $user = $event->user instanceof User ? $event->user : null;

            AuditLogger::logLoginFailed($email, 'invalid_credentials', $user);
        });

        Event::listen(ValidTwoFactorAuthenticationCodeProvided::class, function (ValidTwoFactorAuthenticationCodeProvided $event): void {
            if ($event->user instanceof User) {
                AuditLogger::logTwoFactorChallengeSuccess($event->user);
            }
        });

        Event::listen(TwoFactorAuthenticationFailed::class, function (TwoFactorAuthenticationFailed $event): void {
            if ($event->user instanceof User) {
                AuditLogger::logTwoFactorChallengeFailed($event->user);
            }
        });
    }
}
