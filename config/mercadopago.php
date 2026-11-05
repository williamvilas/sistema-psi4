<?php

declare(strict_types=1);

return [
    // Credenciais da própria conta Mercado Pago da PSI4 (usadas na assinatura SaaS
    // dos planos FREE/ADVANCED e como fallback de leitura para webhooks de repasse).
    'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
    'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),

    // Mercado Pago não possui ambiente de sandbox: a diferença entre teste e
    // produção é apenas qual credencial está configurada (TEST-/APP_USR- de um
    // usuário de teste vs. credencial real). Esta flag é apenas informativa/log.
    'sandbox' => (bool) env('MERCADOPAGO_SANDBOX', false),

    // Segredo usado para validar o header `x-signature` (HMAC-SHA256) de todas
    // as notificações webhook enviadas pela aplicação Mercado Pago da PSI4.
    'webhook_key' => env('MERCADOPAGO_WEBHOOK_KEY'),

    'timeout' => 15,

    // Taxas do próprio Mercado Pago (%), só para informar a terapeuta: quem desconta é o
    // Mercado Pago, e a taxa do PSI4 fica em `plans.plans.*.payment_fee_percent`. Ao mudar
    // algum valor, atualizar também `checked_at` (data exibida junto das taxas).
    'fees' => [
        'checked_at' => '2026-10-31',
        'pix' => 0.99,
        'card' => 4.98,
        'card_release_days' => 7,
        // Parcelado Vendedor: a terapeuta assume os juros do parcelamento
        'installments' => [
            ['from' => 2, 'to' => 6, 'percent' => 2.99],
            ['from' => 7, 'to' => 12, 'percent' => 3.09],
            ['from' => 13, 'to' => 18, 'percent' => 3.09],
        ],
        // Parcelado Comprador: o paciente assume os juros e a taxa pode chegar a este valor
        'installments_patient_max' => 4.98,
    ],

    // Loga em nível debug o cURL completo (headers e body SEM máscara, incluindo
    // token/Authorization) de cada requisição enviada ao Mercado Pago. Serve só
    // para depuração local — NUNCA habilitar em produção, pois expõe credenciais
    // em texto puro no log.
    'debug' => (bool) env('MERCADOPAGO_DEBUG', false),

    // Credenciais OAuth da aplicação Mercado Pago da PSI4, usadas para conectar
    // a conta Mercado Pago de cada terapeuta (Mercado Pago Connect / split payments).
    'oauth' => [
        'client_id' => env('MERCADOPAGO_CLIENT_ID'),
        'client_secret' => env('MERCADOPAGO_CLIENT_SECRET'),
        'redirect_uri' => env('MERCADOPAGO_OAUTH_REDIRECT_URI'),
    ],
];
