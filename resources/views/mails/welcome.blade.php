<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>Bem-vindo ao {{ config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background-color:#eef3f4;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;line-height:1.6;-webkit-text-size-adjust:100%;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
        {{ __('welcome.preheader') }}
    </div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#eef3f4;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">
                    <tr>
                        <td align="center" style="padding:0 0 24px;">
                            <img src="{{ asset('imgs/logos/logo-fundo-branco.png') }}" width="190" height="83" alt="{{ config('app.name') }}" style="display:block;border:0;margin:0 auto;">
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#ffffff;border-radius:16px;border-top:4px solid #2fa595;padding:40px 36px;box-shadow:0 4px 24px rgba(27,50,60,0.08);">
                            <h2 style="color:#1a3b44;margin:0 0 16px;font-size:24px;line-height:1.3;">{{ $firstName ? __('welcome.greeting', ['name' => $firstName]) : __('welcome.greeting_no_name') }}</h2>
                            <p style="color:#333333;font-size:16px;margin:0 0 12px;">{{ __('welcome.thanks') }}</p>
                            <p style="color:#333333;font-size:16px;margin:0 0 8px;">{!! __('welcome.support') !!}</p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" style="padding:28px 0;">
                                        <a href="{{ $whatsappUrl }}" style="background-color:#2c7a7b;color:#ffffff;text-decoration:none;padding:16px 36px;border-radius:10px;font-weight:600;font-size:17px;display:inline-block;">
                                            {{ __('welcome.cta') }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="color:#333333;font-size:15px;margin:0 0 28px;">
                                {{ __('welcome.login_hint') }}
                                <a href="{{ route('login') }}" style="color:#2c7a7b;font-weight:600;">{{ __('welcome.login_link') }}</a>
                            </p>
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding-right:16px;vertical-align:middle;">
                                        <img src="{{ asset('imgs/team/william-oculos-02.png') }}" width="80" height="80" alt="{{ $supportName }}" style="display:block;border:0;border-radius:50%;">
                                    </td>
                                    <td style="vertical-align:middle;color:#333333;font-size:16px;line-height:1.5;">
                                        {{ __('welcome.signature') }}<br>
                                        <strong>{{ $supportName }}</strong>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:20px 8px 0;color:#8a9ba1;font-size:12px;">
                            &copy; {{ date('Y') }} {{ config('app.name') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
