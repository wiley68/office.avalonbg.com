<?php

namespace App\Support;

use App\Enums\AuditEventSource;
use App\Enums\AuditEventType;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public static function resolveSource(?Request $request = null): AuditEventSource
    {
        $request ??= request();

        if ($request->is('api/*')) {
            return AuditEventSource::Api;
        }

        return AuditEventSource::Office;
    }

    public static function logLoginSuccess(User $user, ?AuditEventSource $source = null): void
    {
        self::persist(
            type: AuditEventType::LoginSuccess,
            success: true,
            source: $source ?? AuditEventSource::Office,
            actor: $user,
            details: [
                ['поле' => 'имейл', 'стойност' => $user->email],
            ],
        );
    }

    public static function logLoginFailed(
        string $email,
        string $reason,
        ?User $user = null,
        ?AuditEventSource $source = null,
    ): void {
        self::persist(
            type: AuditEventType::LoginFailed,
            success: false,
            source: $source ?? AuditEventSource::Office,
            actor: $user,
            email: $email,
            name: $user?->name,
            details: [
                ['поле' => 'имейл', 'стойност' => $email],
                ['поле' => 'причина', 'стойност' => $reason],
            ],
        );
    }

    public static function logTwoFactorChallengeSuccess(User $user, ?AuditEventSource $source = null): void
    {
        self::persist(
            type: AuditEventType::TwoFactorChallengeSuccess,
            success: true,
            source: $source ?? AuditEventSource::Office,
            actor: $user,
            details: [
                ['поле' => 'имейл', 'стойност' => $user->email],
            ],
        );
    }

    public static function logTwoFactorChallengeFailed(User $user, ?AuditEventSource $source = null): void
    {
        self::persist(
            type: AuditEventType::TwoFactorChallengeFailed,
            success: false,
            source: $source ?? AuditEventSource::Office,
            actor: $user,
            details: [
                ['поле' => 'имейл', 'стойност' => $user->email],
                ['поле' => 'причина', 'стойност' => 'невалиден MFA код'],
            ],
        );
    }

    /**
     * @param  list<array{поле: string, стойност?: string|null, начална_стойност?: string|null, крайна_стойност?: string|null}>  $details
     */
    private static function persist(
        AuditEventType $type,
        bool $success,
        AuditEventSource $source,
        ?User $actor = null,
        ?string $email = null,
        ?string $name = null,
        array $details = [],
    ): void {
        $resolvedActor = $actor ?? (Auth::user() instanceof User ? Auth::user() : null);

        AuditLog::create([
            'occurred_at' => now(),
            'event_type' => $type,
            'event_source' => $source,
            'is_success' => $success,
            'user_id' => $resolvedActor?->id,
            'user_email' => $email ?? $resolvedActor?->email ?? '—',
            'user_name' => $name ?? $resolvedActor?->name ?? '—',
            'description' => json_encode($details, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
