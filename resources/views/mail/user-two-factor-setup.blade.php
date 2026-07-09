<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ \App\Support\Translations::get('users.mail.title') }}</title>
</head>

<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#18181b;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
        style="background-color:#f4f4f5;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0"
                    style="max-width:600px;width:100%;background-color:#ffffff;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="padding:32px 32px 16px;">
                            <h1 style="margin:0 0 8px;font-size:22px;line-height:1.3;">
                                {{ \App\Support\Translations::get('users.mail.heading') }}</h1>
                            @if ($forAdministrator)
                                <p style="margin:0;color:#52525b;font-size:15px;line-height:1.6;">
                                    @if ($userEmail)
                                                                {{ \App\Support\Translations::get('users.mail.greeting_admin', [
                                            'initiatorName' => $initiatorName,
                                            'userName' => $userName,
                                            'userEmail' => $userEmail,
                                        ]) }}
                                    @else
                                                                {{ \App\Support\Translations::get('users.mail.greeting_admin_no_email', [
                                            'initiatorName' => $initiatorName,
                                            'userName' => $userName,
                                        ]) }}
                                    @endif
                                </p>
                            @else
                                                        <p style="margin:0;color:#52525b;font-size:15px;line-height:1.6;">
                                                            {{ \App\Support\Translations::get('users.mail.greeting_user', [
                                    'userName' => $userName,
                                    'app' => $appName,
                                ]) }}
                                                        </p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 16px;">
                            <h2 style="margin:0 0 12px;font-size:16px;">
                                {{ \App\Support\Translations::get('users.mail.how_to_title') }}</h2>
                            <ol style="margin:0;padding-left:20px;color:#3f3f46;font-size:14px;line-height:1.7;">
                                <li>{{ \App\Support\Translations::get('users.mail.step_install') }}</li>
                                <li>{{ \App\Support\Translations::get('users.mail.step_add') }}</li>
                                <li>{{ \App\Support\Translations::get('users.mail.step_save') }}</li>
                                <li>{{ \App\Support\Translations::get('users.mail.step_login') }}</li>
                            </ol>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:16px 32px;">
                            <p style="margin:0 0 12px;font-size:14px;color:#52525b;">
                                {{ \App\Support\Translations::get('users.mail.qr_label') }}</p>
                            <img src="data:image/svg+xml;base64,{{ base64_encode($qrCodeSvg) }}" alt="QR" width="192"
                                height="192" style="display:block;border:1px solid #e4e4e7;border-radius:8px;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 16px;">
                            <p style="margin:0 0 8px;font-size:14px;color:#52525b;">
                                {{ \App\Support\Translations::get('users.mail.manual_key_intro') }}</p>
                            <p
                                style="margin:0;padding:12px 16px;background-color:#f4f4f5;border-radius:8px;font-family:Consolas,Monaco,monospace;font-size:16px;letter-spacing:1px;word-break:break-all;">
                                {{ $secretKey }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 16px;">
                            <h2 style="margin:0 0 12px;font-size:16px;">
                                {{ \App\Support\Translations::get('users.mail.recovery_title') }}</h2>
                            <p style="margin:0 0 12px;color:#52525b;font-size:14px;line-height:1.6;">
                                {{ \App\Support\Translations::get('users.mail.recovery_description') }}
                            </p>
                            <ul
                                style="margin:0;padding-left:20px;font-family:Consolas,Monaco,monospace;font-size:14px;line-height:1.8;color:#18181b;">
                                @foreach ($recoveryCodes as $code)
                                    <li>{{ $code }}</li>
                                @endforeach
                            </ul>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 32px;">
                            <p style="margin:0 0 16px;color:#52525b;font-size:14px;line-height:1.6;">
                                {{ \App\Support\Translations::get('users.mail.login_intro') }}
                                <a href="{{ $loginUrl }}" style="color:#2563eb;">{{ $loginUrl }}</a>
                            </p>
                            <p style="margin:0;color:#71717a;font-size:12px;line-height:1.6;">
                                {{ \App\Support\Translations::get('users.mail.footer') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
