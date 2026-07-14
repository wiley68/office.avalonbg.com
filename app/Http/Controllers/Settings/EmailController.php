<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TestImapConnectionRequest;
use App\Http\Requests\Settings\UpdateImapAccountRequest;
use App\Models\User;
use App\Models\UserImapAccount;
use App\Services\ImapMailboxService;
use App\Support\Translations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()?->isOfficeUser() ?? false, 403);

        $account = $request->user()?->imapAccount;

        return Inertia::render('settings/Email', [
            'imapAccount' => $account ? [
                'host' => $account->host,
                'port' => $account->port,
                'encryption' => $account->encryption->value,
                'username' => $account->username,
                'default_folder' => $account->default_folder,
                'has_password' => filled($account->password),
                'last_verified_at' => $account->last_verified_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function update(UpdateImapAccountRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $attributes = $request->safe()->except('password');

        if ($request->filled('password')) {
            $attributes['password'] = $request->validated('password');
        }

        $user->imapAccount()->updateOrCreate(
            ['user_id' => $user->id],
            $attributes,
        );

        return to_route('email.edit');
    }

    public function testConnection(TestImapConnectionRequest $request, ImapMailboxService $imapMailboxService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $account = $this->resolveAccountForConnectionTest($request, $user);

        try {
            $imapMailboxService->testConnection($account);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => Translations::get('settings.email.test_success'),
        ]);
    }

    private function resolveAccountForConnectionTest(TestImapConnectionRequest $request, User $user): UserImapAccount
    {
        $existingAccount = $user->imapAccount;

        abort_if($existingAccount === null && ! $request->filled('password'), 422, Translations::get('settings.email.errors.not_configured'));

        $account = $existingAccount ?? new UserImapAccount([
            'user_id' => $user->id,
        ]);

        $account->fill($request->safe()->except('password'));

        if ($request->filled('password')) {
            $account->password = $request->validated('password');
        }

        return $account;
    }
}
