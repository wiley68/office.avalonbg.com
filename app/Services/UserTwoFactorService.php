<?php

namespace App\Services;

use App\Mail\UserTwoFactorSetupMail;
use App\Models\User;
use App\Support\Translations;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use Throwable;

class UserTwoFactorService
{
    public function __construct(
        private EnableTwoFactorAuthentication $enableTwoFactor,
        private DisableTwoFactorAuthentication $disableTwoFactor,
    ) {}

    /**
     * @return array{
     *     email_sent: bool,
     *     email_sent_to: string|null,
     *     email_recipient: 'user'|'creator'|null,
     *     email_error: string|null,
     *     setup_data: array{
     *         secretKey: string,
     *         qrCodeSvg: string,
     *         recoveryCodes: array<int, string>
     *     }|null
     * }
     */
    public function enableAndTrySendSetupMail(User $user, User $initiator): array
    {
        ($this->enableTwoFactor)($user, force: true);

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $this->trySendSetupMail($user->fresh(), $initiator);
    }

    public function disable(User $user): void
    {
        ($this->disableTwoFactor)($user);
    }

    /**
     * @return array{
     *     email_sent: bool,
     *     email_sent_to: string|null,
     *     email_recipient: 'user'|'creator'|null,
     *     email_error: string|null,
     *     setup_data: array{
     *         secretKey: string,
     *         qrCodeSvg: string,
     *         recoveryCodes: array<int, string>
     *     }|null
     * }
     */
    public function trySendSetupMail(User $user, User $initiator): array
    {
        if (is_null($user->two_factor_secret)) {
            abort(422, Translations::get('users.two_factor.not_enabled'));
        }

        $userEmailError = null;

        if ($this->isDeliverableEmail($user->email)) {
            $userAttempt = $this->attemptSendToRecipient($user, $user->email);

            if ($userAttempt['sent']) {
                return [
                    'email_sent' => true,
                    'email_sent_to' => $user->email,
                    'email_recipient' => 'user',
                    'email_error' => null,
                    'setup_data' => null,
                ];
            }

            $userEmailError = $userAttempt['error'];
        } else {
            $userEmailError = blank($user->email)
                ? Translations::get('users.two_factor.user_missing_email')
                : Translations::get('users.two_factor.user_invalid_email');
        }

        if ($this->canSendToInitiator($user, $initiator)) {
            $initiatorAttempt = $this->attemptSendToRecipient(
                $user,
                $initiator->email,
                forAdministrator: true,
                initiatorName: $initiator->name,
            );

            if ($initiatorAttempt['sent']) {
                return [
                    'email_sent' => true,
                    'email_sent_to' => $initiator->email,
                    'email_recipient' => 'creator',
                    'email_error' => null,
                    'setup_data' => null,
                ];
            }

            return $this->manualSetupResult(
                $user,
                $initiatorAttempt['error'] ?? Translations::get('users.two_factor.email_send_failed'),
            );
        }

        return $this->manualSetupResult($user, $userEmailError);
    }

    /**
     * @return array{
     *     secretKey: string,
     *     qrCodeSvg: string,
     *     qrCodeUrl: string,
     *     recoveryCodes: array<int, string>
     * }
     */
    public function setupData(User $user): array
    {
        return [
            'secretKey' => Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
            'qrCodeSvg' => $user->twoFactorQrCodeSvg(),
            'qrCodeUrl' => $user->twoFactorQrCodeUrl(),
            'recoveryCodes' => $user->recoveryCodes(),
        ];
    }

    public static function formatSecretKey(string $secretKey): string
    {
        return trim(chunk_split($secretKey, 4, ' '));
    }

    private function isDeliverableEmail(?string $email): bool
    {
        return filled($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function canSendToInitiator(User $user, User $initiator): bool
    {
        if (! $this->isDeliverableEmail($initiator->email)) {
            return false;
        }

        return strtolower($initiator->email) !== strtolower((string) $user->email);
    }

    /**
     * @return array{sent: bool, error: string|null}
     */
    private function attemptSendToRecipient(
        User $user,
        string $email,
        bool $forAdministrator = false,
        ?string $initiatorName = null,
    ): array {
        try {
            Mail::to($email)->send(new UserTwoFactorSetupMail(
                $user,
                forAdministrator: $forAdministrator,
                initiatorName: $initiatorName,
            ));

            return [
                'sent' => true,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'sent' => false,
                'error' => Translations::get('users.two_factor.email_send_failed'),
            ];
        }
    }

    /**
     * @return array{
     *     email_sent: bool,
     *     email_sent_to: string|null,
     *     email_recipient: 'user'|'creator'|null,
     *     email_error: string|null,
     *     setup_data: array{
     *         secretKey: string,
     *         qrCodeSvg: string,
     *         recoveryCodes: array<int, string>
     *     }
     * }
     */
    private function manualSetupResult(User $user, string $error): array
    {
        $setupData = $this->setupData($user);

        return [
            'email_sent' => false,
            'email_sent_to' => null,
            'email_recipient' => null,
            'email_error' => $error,
            'setup_data' => [
                'secretKey' => self::formatSecretKey($setupData['secretKey']),
                'qrCodeSvg' => $setupData['qrCodeSvg'],
                'recoveryCodes' => $setupData['recoveryCodes'],
            ],
        ];
    }
}
