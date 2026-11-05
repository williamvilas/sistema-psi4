<?php

declare(strict_types=1);

return [
    // Desligada até a Fase 0 validar captura, qualidade e contrato. Ligada, a função aparece nos planos que têm cota
    'enabled' => (bool) env('PSI_TRANSCRIPTION_ENABLED', false),
    // Bucket só dos arquivos temporários (áudio e saída do Transcribe), com SSE-KMS, ciclo de vida de 24 h e política que
    // nega leitura humana. O rascunho cifrado não fica aqui: vai para o disco padrão, como os prontuários.
    's3' => [
        'bucket' => env('PSI_TRANSCRIPTION_BUCKET'),
        'kms_key_id' => env('PSI_TRANSCRIPTION_KMS_KEY_ID'),
        'audio_prefix' => 'transcription-audio',
        'text_prefix' => 'transcription-text',
    ],
    'aws' => [
        'region' => env('PSI_TRANSCRIBE_AWS_REGION', 'sa-east-1'),
        // Só assina o PUT do áudio no prefixo de áudio. Não lê nada
        'uploader' => [
            'key' => env('PSI_TRANSCRIPTION_UPLOADER_AWS_ACCESS_KEY_ID'),
            'secret' => env('PSI_TRANSCRIPTION_UPLOADER_AWS_SECRET_ACCESS_KEY'),
        ],
        // Fila: Transcribe em lote, leitura e exclusão dos temporários e Bedrock. Não reaproveitar a do S3 dos prontuários
        'worker' => [
            'key' => env('PSI_TRANSCRIPTION_WORKER_AWS_ACCESS_KEY_ID'),
            'secret' => env('PSI_TRANSCRIPTION_WORKER_AWS_SECRET_ACCESS_KEY'),
        ],
    ],
    // O Bedrock pode exigir inferência fora de sa-east-1: o texto do aceite e a política de privacidade já avisam
    'bedrock' => [
        'region' => env('PSI_TRANSCRIPTION_BEDROCK_REGION', env('PSI_TRANSCRIBE_AWS_REGION', 'sa-east-1')),
        'model' => env('PSI_TRANSCRIPTION_BEDROCK_MODEL', 'anthropic.claude-opus-5-5'),
    ],
    'language' => 'pt-BR',
    // Trechos independentes: um perdido não estraga os outros. O servidor aceita um pouco mais que o navegador grava
    'segment_seconds' => 120,
    'segment_max_seconds' => 150,
    'segment_max_bytes' => 2 * 1024 * 1024,
    'segment_max_per_minute' => 3,
    'segment_mimes' => [
        'audio/webm;codecs=opus' => 'webm',
        'audio/ogg;codecs=opus' => 'ogg',
        'audio/mp4' => 'mp4',
    ],
    // Mudar o texto exige subir a versão: o aceite guarda a versão e o hash do que o paciente leu
    'consent_version' => 2,
    'consent_text' => 'Sua(seu) profissional deseja gravar o áudio desta sessão para ajudar a registrar os pontos mais importantes da conversa. O áudio é transcrito e resumido automaticamente pela AWS e apagado após o processamento. Somente seu(sua) profissional terá acesso ao resumo, podendo revisá-lo e decidir o que será incluído no prontuário. O tratamento dos dados segue a LGPD e as normas aplicáveis do CFP. A gravação é opcional e pode ser interrompida a qualquer momento. O trecho já gravado ainda poderá ser utilizado.',
    // Fixo porque medical_records.title não é cifrado
    'record_title' => 'Registro da sessão',
    'review_days' => 30,
    'review_warning_days' => [7, 1],
    // Depois do fim da sala, o que ficou aberto vai para a fila ou é descartado
    'close_after_hours' => 6,
    // O ciclo de vida do bucket apaga o áudio em 24 h: o processamento tem de acabar antes
    'processing_hours' => 20,
];
