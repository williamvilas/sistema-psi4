<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperação da autenticação de 2 fatores</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;line-height:1.6;">
    <div style="max-width:560px;margin:30px auto;background:#ffffff;border-radius:8px;padding:32px;">
        <h2 style="color:#1a3b44;margin-top:0;">Olá, {{ $name }}!</h2>
        <p style="color:#333;">Recebemos uma solicitação para desativar a autenticação de 2 fatores da sua conta no {{ config('app.name') }}.</p>
        <p style="color:#333;">Para continuar, clique no botão abaixo. O link é válido por {{ $expiresInMinutes }} minutos e só pode ser usado uma vez.</p>
        <p style="text-align:center;margin:28px 0;">
            <a href="{{ $url }}" style="background:#2c7a7b;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:600;display:inline-block;">
                Desativar autenticação de 2 fatores
            </a>
        </p>
        <p style="color:#666;font-size:14px;">Se você não fez essa solicitação, ignore este e-mail e considere alterar a sua senha.</p>
    </div>
</body>
</html>
