<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento | PSI4</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #FAFCFB;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .confirmation-card {
            max-width: 420px;
            width: 100%;
            text-align: center;
            padding: 2.5rem 2rem;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            background: #FFFFFF;
        }
        .confirmation-icon {
            font-size: 3.5rem;
            margin-bottom: 1rem;
        }
        .icon-success { color: #10b981; }
        .icon-failure { color: #dc3545; }
        .icon-pending { color: #d4a83c; }
    </style>
</head>
<body>
    @php
        $content = match ($status) {
            'success' => ['icon' => 'bi-check-circle-fill', 'class' => 'icon-success', 'title' => 'Pagamento aprovado!', 'message' => 'Recebemos a confirmação do seu pagamento. Obrigado!'],
            'failure' => ['icon' => 'bi-x-circle-fill', 'class' => 'icon-failure', 'title' => 'Pagamento não aprovado', 'message' => 'Não foi possível concluir o pagamento. Tente novamente ou entre em contato com o profissional.'],
            default => ['icon' => 'bi-hourglass-split', 'class' => 'icon-pending', 'title' => 'Pagamento pendente', 'message' => 'Seu pagamento está sendo processado. Você será notificado assim que for confirmado.'],
        };
    @endphp

    <div class="confirmation-card">
        <div class="confirmation-icon {{ $content['class'] }}">
            <i class="bi {{ $content['icon'] }}"></i>
        </div>
        <h1 class="h4 fw-bold mb-2">{{ $content['title'] }}</h1>
        <p class="text-muted mb-0">{{ $content['message'] }}</p>
    </div>
</body>
</html>
