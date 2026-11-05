<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Erro no Servidor - {{ config('app.name') }}</title>
    <link href="{{ asset('css/tailwind.min.css') }}" rel="stylesheet">
    <link rel="icon" href="{{ asset(config('logos.favicon')) }}" type="image/x-icon">
</head>
<body class="bg-gray-100 text-gray-800 flex items-center justify-center min-h-screen px-4">
<div class="text-center max-w-lg">
    <h1 class="text-7xl font-bold text-red-600 mb-4">403</h1>
    <h2 class="text-2xl font-semibold mb-2">Erro no Servidor</h2>
    <p class="text-gray-600 mb-6">
        Ocorreu um erro inesperado no servidor. Nossa equipe já foi notificada e está trabalhando para resolver o problema.
    </p>
</div>
</body>
</html>
