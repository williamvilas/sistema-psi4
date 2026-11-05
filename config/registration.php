<?php

declare(strict_types=1);

return [
    // Com true, só quem tem convite válido cria conta (e-mail/senha ou Google/LinkedIn).
    // Quem não tem convite é levada à página que pede Nome e WhatsApp.
    'invite_only' => (bool) env('REGISTRATION_INVITE_ONLY', false),

    // Dias de validade de cada convite gerado.
    'invite_ttl_days' => (int) env('REGISTRATION_INVITE_TTL_DAYS', 14),

    // Chave da sessão que guarda o convite entre a abertura do link e o cadastro.
    'session_key' => 'registration_invite',
];
