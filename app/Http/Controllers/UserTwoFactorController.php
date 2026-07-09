<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserTwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class UserTwoFactorController extends Controller
{
    public function enable(User $user, UserTwoFactorService $twoFactorService): RedirectResponse
    {
        $this->authorize('update', $user);

        /** @var User $initiator */
        $initiator = Auth::user();

        $result = $twoFactorService->enableAndTrySendSetupMail($user, $initiator);

        $this->flashSetupResult($result);

        return back();
    }

    public function disable(User $user, UserTwoFactorService $twoFactorService): RedirectResponse
    {
        $this->authorize('update', $user);

        $twoFactorService->disable($user);

        return back();
    }

    public function resend(User $user, UserTwoFactorService $twoFactorService): RedirectResponse
    {
        $this->authorize('update', $user);

        /** @var User $initiator */
        $initiator = Auth::user();

        $result = $twoFactorService->trySendSetupMail($user, $initiator);

        $this->flashSetupResult($result);

        return back();
    }

    /**
     * @param  array{
     *     email_sent: bool,
     *     email_sent_to: string|null,
     *     email_recipient: 'user'|'creator'|null,
     *     email_error: string|null,
     *     setup_data: array{
     *         secretKey: string,
     *         qrCodeSvg: string,
     *         recoveryCodes: array<int, string>
     *     }|null
     * }  $result
     */
    private function flashSetupResult(array $result): void
    {
        if ($result['email_sent'] && $result['email_recipient'] === 'creator') {
            Inertia::flash('two_factor_notification', [
                'type' => 'sent_to_creator',
                'sent_to' => $result['email_sent_to'],
            ]);

            return;
        }

        if ($result['email_sent'] || $result['setup_data'] === null) {
            return;
        }

        Inertia::flash('two_factor_manual_setup', [
            'reason' => $result['email_error'],
            ...$result['setup_data'],
        ]);
    }
}
