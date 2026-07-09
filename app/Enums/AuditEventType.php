<?php

namespace App\Enums;

enum AuditEventType: string
{
    case LoginSuccess = 'login_success';
    case LoginFailed = 'login_failed';
    case TwoFactorChallengeSuccess = 'two_factor_challenge_success';
    case TwoFactorChallengeFailed = 'two_factor_challenge_failed';

    public function label(): string
    {
        return match ($this) {
            self::LoginSuccess => 'Успешен вход',
            self::LoginFailed => 'Неуспешен опит за вход',
            self::TwoFactorChallengeSuccess => 'Успешен MFA challenge',
            self::TwoFactorChallengeFailed => 'Неуспешен MFA challenge',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $case) => $case->value, self::cases()),
            array_map(fn (self $case) => $case->label(), self::cases()),
        );
    }
}
