<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset(config('logos.favicon')) }}" type="image/x-icon">
    <link rel="stylesheet" href="{{ asset('css/font-awesome-6.0.min.css') }}">
    <script src="{{ asset('js/tailwind.js') }}"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&display=swap');

        .legal-container { max-width: 800px; margin: 0 auto; }
        .section-title { position: relative; padding-left: 1.5rem; }
        .section-title:before {
            content: "";
            position: absolute;
            left: 0;
            top: 0.5em;
            height: 1em;
            width: 0.5em;
            background-color: #0d9488;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">
<div class="container mx-auto px-4 py-12">
    <div class="legal-container bg-white shadow-md rounded-lg p-8">
        <div class="text-center mb-12">
            <a href="{{ route('page-main') }}" class="inline-block p-4 mb-4">
                <x-logo-psi4 :size="48" text="#1b323c" accent="#2fa595" />
            </a>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">@yield('title')</h1>
            <p class="text-sm text-gray-500 mt-4">Última atualização: {{ config('info.legal.updated_at') }}</p>
        </div>

        @yield('content')

        <div class="mb-8">
            <h2 class="text-xl font-bold section-title mb-4">Contato</h2>
            <div class="bg-teal-50 p-4 rounded-lg space-y-2">
                <p><i class="fa-brands fa-whatsapp text-teal-700 mr-2"></i><strong>WhatsApp:</strong>
                    <a href="{{ generateLinkWhatsApp('Oi, preciso de ajuda') }}" target="_blank" rel="noopener" class="text-teal-700 hover:underline">{{ formatPhone(config('info.whatsapp')) }}</a>
                </p>
                @if(config('info.legal.dpo_email'))
                    <p><i class="fas fa-envelope text-teal-700 mr-2"></i><strong>Encarregado de dados (DPO):</strong>
                        <a href="mailto:{{ config('info.legal.dpo_email') }}" class="text-teal-700 hover:underline">{{ config('info.legal.dpo_email') }}</a>
                    </p>
                @endif
            </div>
        </div>

        <div class="border-t border-gray-200 pt-6 flex flex-wrap justify-between gap-3 text-sm">
            <a href="{{ route('terms-service') }}" class="text-teal-700 hover:underline">Termos de Uso</a>
            <a href="{{ route('privacy-policy') }}" class="text-teal-700 hover:underline">Política de Privacidade</a>
            <a href="{{ route('security') }}" class="text-teal-700 hover:underline">Segurança e Privacidade</a>
            <a href="{{ route('page-main') }}" class="text-teal-700 hover:underline">Voltar ao início</a>
        </div>
    </div>
</div>
</body>
</html>
