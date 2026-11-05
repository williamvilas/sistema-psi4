<?php

declare(strict_types=1);

return [
    // URL pública do Jitsi usada para montar o link da sala (sem barra no final)
    'jitsi_base_url' => rtrim((string) env('PSI_VIDEO_JITSI_BASE_URL', 'https://meet.psi4.com.br'), '/'),
    // Credenciais do Prosody (mod_auth_token): devem ser iguais a app_id/app_secret do Prosody
    'jitsi' => [
        'app_id' => env('PSI_VIDEO_JITSI_APP_ID'),
        'app_secret' => env('PSI_VIDEO_JITSI_APP_SECRET'),
        'sub' => env('PSI_VIDEO_JITSI_SUB', '*'),
        'aud' => env('PSI_VIDEO_JITSI_AUD', 'jitsi'),
    ],
    // Token (Bearer) exigido nas rotas internas /api/video-rooms
    'api_token' => env('PSI_VIDEO_API_TOKEN'),
    // Chaves dos HMACs de CPF e telefone. Trocar invalida as credenciais daquele tipo nas salas existentes
    'cpf_pepper' => env('PSI_VIDEO_CPF_PEPPER'),
    'phone_pepper' => env('PSI_VIDEO_PHONE_PEPPER'),
    // Janela de acesso em minutos. Ausente ou vazia = sem limite naquele lado (0 = exatamente no horário)
    'access_opens_before_minutes' => is_numeric(env('PSI_VIDEO_ACCESS_OPENS_BEFORE_MINUTES')) ? (int) env('PSI_VIDEO_ACCESS_OPENS_BEFORE_MINUTES') : null,
    'access_closes_after_minutes' => is_numeric(env('PSI_VIDEO_ACCESS_CLOSES_AFTER_MINUTES')) ? (int) env('PSI_VIDEO_ACCESS_CLOSES_AFTER_MINUTES') : null,
    // Segredo das dinâmicas (autorização de gravação e chave do rascunho no navegador). Trocar invalida rascunhos abertos
    'activity_draft_secret' => env('PSI_ACTIVITY_DRAFT_SECRET'),
    'cors_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('PSI_VIDEO_CORS_ORIGINS', ''))))),
];
