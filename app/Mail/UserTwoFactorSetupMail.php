<?php

namespace App\Mail;

use App\Models\User;
use App\Services\UserTwoFactorService;
use App\Support\Translations;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserTwoFactorSetupMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public bool $forAdministrator = false,
        public ?string $initiatorName = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->forAdministrator
            ? Translations::get('users.mail.subject_admin', ['name' => $this->user->name])
            : Translations::get('users.mail.subject_user', ['app' => config('app.name')]);

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $service = app(UserTwoFactorService::class);
        $setupData = $service->setupData($this->user);

        return new Content(
            view: 'mail.user-two-factor-setup',
            with: [
                'userName' => $this->user->name,
                'userEmail' => $this->user->email,
                'appName' => config('app.name'),
                'loginUrl' => route('login'),
                'secretKey' => UserTwoFactorService::formatSecretKey($setupData['secretKey']),
                'qrCodeSvg' => $setupData['qrCodeSvg'],
                'recoveryCodes' => $setupData['recoveryCodes'],
                'forAdministrator' => $this->forAdministrator,
                'initiatorName' => $this->initiatorName,
            ],
        );
    }
}
