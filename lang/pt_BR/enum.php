<?php

declare(strict_types=1);

return [
    'swing_trade' => 'Swing Trade',
    'day_trade' => 'Day Trade',
    'options' => 'Opções',
    'long' => 'Comprado',
    'short' => 'Vendido',
    'demo' => 'Conta Demo',
    'real' => 'Conta Real',
    'mercado_pago' => 'Mercado Pago',
    'SWING_TRADE' => 'Swing Trade',
    'DAY_TRADE' => 'Day Trade',
    'OPTIONS' => 'Opções',
    'LONG' => 'Comprado',
    'SHORT' => 'Vendido',
    'DEMO' => 'Conta Demo',
    'REAL' => 'Conta Real',

    'payment_account_status' => [
        'pending' => 'Não conectado ao Mercado Pago',
        'processing' => 'Conectando ao Mercado Pago',
        'integrated' => 'Conta conectada',
        'error' => 'Erro ao conectar com o Mercado Pago',
        'needs_reauth' => 'Reconexão necessária',
    ],

    'google_calendar_status' => [
        'pending' => 'Não conectado ao Google',
        'integrated' => 'Conta conectada',
        'needs_reauth' => 'Reconexão necessária',
    ],

    'payment_order_integration_status' => [
        'pending' => 'Pendente de integração',
        'processing' => 'Integração em andamento',
        'integrated' => 'Integração concluída',
        'error' => 'Falha na integração',
    ],

    'payment_order_status' => [
        'pending' => 'Pendente',
        'paid' => 'Pago',
        'processing' => 'Processando',
        'cancelled' => 'Cancelado',
        'reversing' => 'Reembolsando',
        'refunded' => 'Reembolsado',
        'rejected' => 'Rejeitado',
        'expired' => 'Expirado',
    ],

    'payment_method' => [
        'pix' => 'PIX',
        'card' => 'Cartão',
    ],
    'payment_method_description' => [
        'pix' => 'Aprovação na hora',
        'card' => 'Crédito ou débito',
    ],

    'health_receipt_status' => [
        'pending' => 'A emitir',
        'exported' => 'No arquivo',
        'cancelled' => 'Não emitir',
    ],
    'health_receipt_source' => [
        'payment_order' => 'Cobrança pelo PSI4',
        'manual' => 'Registrado por você',
    ],
    'health_receipt_batch_status' => [
        'generated' => 'Aguardando importação',
        'imported' => 'Importado no e-CAC',
        'undone' => 'Desfeito',
    ],

    'plan_subscription_status' => [
        'pending' => 'Aguardando confirmação no Mercado Pago',
        'authorized' => 'Ativa',
        'paused' => 'Pausada',
        'cancelled' => 'Cancelada',
    ],

    'notification_status' => [
        'pending' => 'Pendente',
        'processing' => 'Enviando',
        'error' => 'Erro',
        'done' => 'Enviada',
    ],

    'notification_channel' => [
        'whatsapp' => 'WhatsApp',
        'sms' => 'SMS',
        'email' => 'E-mail',
    ],

    'notification_type' => [
        'agenda_scheduled' => 'Sessão agendada',
        'agenda_canceled' => 'Sessão cancelada',
        'agenda_no_show' => 'Falta na sessão',
        'agenda_completed' => 'Sessão concluída',
        'agenda_meet_missing' => 'Link do Meet pendente',
        'agenda_attendance' => 'Presença pendente',
        'agenda_reminder' => 'Lembrete do horário',
        'agenda_room_link' => 'Link da sala',
        'agenda_reschedule' => 'Pedido para remarcar',
    ],

    'description' => [
        'EWZ' => 'Representa a bolsa brasileira no mercado americano.',
    ],
];
