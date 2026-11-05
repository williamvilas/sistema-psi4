<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>Renovação do plano cancelada</title>
</head>
<body style="margin:0;padding:0;background-color:#eef3f4;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;line-height:1.6;-webkit-text-size-adjust:100%;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
        Você cancelou a renovação do seu plano. Ele continua ativo até {{ $expirationDate }}.
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
                            <h2 style="color:#1a3b44;margin:0 0 16px;font-size:24px;line-height:1.3;">Olá, {{ $name }}!</h2>
                            <p style="color:#333333;font-size:16px;margin:0 0 12px;">
                                Confirmamos o cancelamento da renovação do seu plano no {{ config('app.name') }}.
                            </p>
                            <p style="color:#333333;font-size:16px;margin:0 0 24px;">
                                Você continua com todos os recursos até <strong>{{ $expirationDate }}</strong>. Depois dessa data, sua conta volta para o plano Grátis, com todos os seus registros guardados e editáveis, e nenhuma nova cobrança será feita.
                            </p>
                            <div style="border-top:1px solid #e3ebed;padding-top:20px;">
                                <p style="color:#666666;font-size:14px;margin:0;">Se você não fez esse cancelamento, entre em contato com o nosso suporte.</p>
                            </div>
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
