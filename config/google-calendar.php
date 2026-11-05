<?php

declare(strict_types=1);

return [
    // Credenciais OAuth da aplicação Google da PSI4, usadas para conectar o
    // Google Calendar de cada psicólogo. Reaproveita o client do login
    // social, mas com redirect URI próprio (cadastrar no Google Cloud Console).
    'oauth' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_CALENDAR_REDIRECT_URI'),
    ],

    // Menor privilégio possível: eventos + listagem dos calendários (somente leitura)
    'scopes' => [
        'openid',
        'email',
        'https://www.googleapis.com/auth/calendar.events',
        'https://www.googleapis.com/auth/calendar.calendarlist.readonly',
        'https://www.googleapis.com/auth/calendar.freebusy',
    ],

    // Escopos sem os quais a conexão é recusada (o usuário pode desmarcá-los na tela de consentimento)
    'required_scopes' => [
        'https://www.googleapis.com/auth/calendar.events',
        'https://www.googleapis.com/auth/calendar.calendarlist.readonly',
        'https://www.googleapis.com/auth/calendar.freebusy',
    ],

    // Escopo que permite consultar só livre/ocupado para bloquear horários
    'freebusy_scope' => 'https://www.googleapis.com/auth/calendar.freebusy',

    // Segundos de cache da consulta de horários ocupados (a página pública é muito acessada)
    'busy_cache_seconds' => 20,

    'timeout' => 15,
];
