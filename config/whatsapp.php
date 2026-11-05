<?php

declare(strict_types=1);

/*
 * Avisos automáticos pela API oficial do WhatsApp (Cloud API da Meta), enviados pelo número oficial da PSI4.
 *
 * Fora da janela de 24h de conversa a Meta só aceita modelos (templates) aprovados. Cada modelo abaixo
 * precisa ser cadastrado no WhatsApp Manager (categoria "Utilidade", idioma pt_BR) com o MESMO texto do
 * slug correspondente em lang/pt_BR/notifications.php, trocando os placeholders pela ordem de `params`:
 * ex.: `params => ['patient', 'psychologist', 'date']` => :patient = {{1}}, :psychologist = {{2}}, :date = {{3}}.
 * Os textos não citam terapia nem sessão: a notificação pode ser vista por terceiros no celular do paciente.
 */
return [
    // Sem envio (as notificações continuam só registradas como pendentes) enquanto estiver desligado
    'enabled' => (bool) env('WHATSAPP_ENABLED', false),

    'api_version' => env('WHATSAPP_API_VERSION', 'v23.0'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),

    // Assinatura `X-Hub-Signature-256` do webhook (segredo do app na Meta) e token da verificação do endpoint
    'app_secret' => env('WHATSAPP_APP_SECRET'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),

    'timeout' => 15,

    'language' => 'pt_BR',

    // Antecedência do envio do link da sala nas consultas online
    'room_link_minutes' => 5,

    // Opções de antecedência do lembrete (minutos) exibidas nas configurações da agenda
    'reminder_options' => [60, 120, 180, 360, 720, 1440, 2880],

    // Lembrete que cairia entre 22h e 7h (fuso da psicóloga) é antecipado para as 21h
    'quiet_hours' => ['start' => 22, 'end' => 7, 'fallback' => 21],

    // Respostas dos botões do lembrete (payload dos botões de resposta rápida)
    'buttons' => ['confirm' => 'confirm', 'reschedule' => 'reschedule'],

    // Palavras que descadastram o paciente dos avisos automáticos
    'opt_out_words' => ['SAIR', 'PARAR', 'STOP'],

    // Destinatário => tipo da notificação => modelo. Tipo sem modelo é só registrado, não é enviado.
    'templates' => [
        'patient' => [
            'agenda_scheduled' => [
                'name' => env('WHATSAPP_TEMPLATE_PATIENT_SCHEDULED', 'psi4_horario_marcado'),
                'params' => ['patient', 'psychologist', 'date'],
            ],
            'agenda_reminder' => [
                'name' => env('WHATSAPP_TEMPLATE_PATIENT_REMINDER', 'psi4_lembrete_horario'),
                'params' => ['patient', 'psychologist', 'date'],
                // Ordem dos botões de resposta rápida cadastrados no modelo
                'buttons' => ['confirm', 'reschedule'],
            ],
            'agenda_room_link' => [
                'name' => env('WHATSAPP_TEMPLATE_PATIENT_ROOM_LINK', 'psi4_link_sala'),
                'params' => ['patient', 'psychologist', 'link'],
            ],
        ],
        'psychologist' => [
            'agenda_scheduled' => [
                'name' => env('WHATSAPP_TEMPLATE_PSYCHOLOGIST_SCHEDULED', 'psi4_novo_agendamento'),
                'params' => ['patient', 'date'],
            ],
            'agenda_room_link' => [
                'name' => env('WHATSAPP_TEMPLATE_PSYCHOLOGIST_ROOM_LINK', 'psi4_link_sala_profissional'),
                'params' => ['patient', 'link'],
            ],
            'agenda_reschedule' => [
                'name' => env('WHATSAPP_TEMPLATE_PSYCHOLOGIST_RESCHEDULE', 'psi4_pedido_remarcar'),
                'params' => ['patient', 'date'],
            ],
        ],
    ],
];
