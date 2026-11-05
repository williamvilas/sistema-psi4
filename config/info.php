<?php

declare(strict_types=1);

return [
    'whatsapp' => '12988840421',

    // Horário do suporte (tela de Ajuda). Chave = dia ISO (1 = segunda ... 7 = domingo); dia ausente = fechado.
    // Feriados nacionais (HolidayHelper) também ficam fechados.
    'support_hours' => [
        'timezone' => 'America/Sao_Paulo',
        'days' => [
            1 => ['08:00', '20:00'],
            2 => ['08:00', '20:00'],
            3 => ['08:00', '20:00'],
            4 => ['08:00', '20:00'],
            5 => ['08:00', '20:00'],
            6 => ['08:00', '12:00'],
        ],
    ],

    // Atendimento: usado no e-mail de boas-vindas (assinatura e Reply-To).
    'support' => [
        'name' => env('SUPPORT_NAME', 'William Vilas Boas'),
        'email' => env('SUPPORT_EMAIL', 'williamhenriquevilasboas@gmail.com'),
    ],

    'full_plan' => [
        'price' => 29.90,
    ],

    // Documentos legais: alterar a versão sempre que os textos mudarem materialmente.
    'legal' => [
        'terms_version' => '2026-10-04',
        'updated_at' => '04/10/2026',
        'company_name' => env('LEGAL_COMPANY_NAME', env('APP_NAME', 'PSI4')),
        'company_document' => env('LEGAL_COMPANY_DOCUMENT'),
        'dpo_email' => env('LEGAL_DPO_EMAIL'),
        'forum' => env('LEGAL_FORUM'),
    ],
];
