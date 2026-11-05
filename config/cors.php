<?php

declare(strict_types=1);

return [
    // Só o acesso às salas de vídeo e as dinâmicas da sala são chamados pelo navegador (front do Jitsi)
    'paths' => ['api/video-room/access', 'api/video-room/activities', 'api/video-room/activities/*', 'api/video-room/transcriptions', 'api/video-room/transcriptions/*'],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    // Mesmo valor de psi_video.cors_origins (config() não está disponível aqui: cors.php carrega antes de psi_video.php)
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('PSI_VIDEO_CORS_ORIGINS', ''))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Accept', 'Authorization'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
