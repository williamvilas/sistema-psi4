<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rascunhos de transcrição aguardando revisão</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;line-height:1.6;">
    <div style="max-width:560px;margin:30px auto;background:#ffffff;border-radius:8px;padding:32px;">
        <h2 style="color:#1a3b44;margin-top:0;">Olá, {{ $name }}!</h2>
        <p style="color:#333;">Você tem rascunho de transcrição aguardando sua revisão. Se não decidir em {{ $days === 1 ? '1 dia' : $days.' dias' }}, o texto será apagado definitivamente.</p>
        <p style="text-align:center;margin:28px 0;">
            <a href="{{ $url }}" style="background:#2c7a7b;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:600;display:inline-block;">
                Revisar rascunhos
            </a>
        </p>
    </div>
</body>
</html>
