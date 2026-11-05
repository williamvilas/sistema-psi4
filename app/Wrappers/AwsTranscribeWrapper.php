<?php

declare(strict_types=1);

namespace App\Wrappers;

use App\Jobs\RegisterLog;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Aws\TranscribeService\TranscribeServiceClient;

/**
 * Áudio temporário da sessão no S3 e transcrição em lote no Amazon Transcribe. O navegador da psicóloga envia o áudio
 * direto ao bucket por URL assinada, sem passar pelo PSI4; a fila lê o resultado, apaga tudo e só guarda o rascunho
 * cifrado com a chave pública dela.
 *
 * Usa o SDK da AWS, não o cliente HTTP do Laravel: a falha é registrada com o código de erro da AWS, nunca com o corpo,
 * porque o corpo é áudio ou fala da sessão.
 */
class AwsTranscribeWrapper
{
    private const string UPLOAD_EXPIRES = '+5 minutes';

    // Nome do objeto: <sequência>-<início em ms>-<duração em ms>.<extensão>. O servidor monta a chave, o navegador não
    private const string SEGMENT_PATTERN = '/(\d{1,4})-(\d{1,9})-(\d{1,9})\.(webm|ogg|mp4)$/';

    /**
     * URL para o navegador enviar um trecho. Só serve para aquele objeto, com aquele tipo e com a chave KMS do bucket.
     *
     * @return array{url: string, headers: array<string, string>}
     */
    public function segmentUploadUrl(string $uuid, int $sequence, int $startMs, int $durationMs, string $mime, string $extension): array
    {
        $client = $this->s3Client('uploader');
        $params = [
            'Bucket' => $this->bucket(),
            'Key' => self::segmentKey($uuid, $sequence, $startMs, $durationMs, $extension),
            'ContentType' => $mime,
            'ServerSideEncryption' => 'aws:kms',
        ];

        if ($kms = config('session_transcription.s3.kms_key_id')) {
            $params['SSEKMSKeyId'] = $kms;
        }

        $request = $client->createPresignedRequest($client->getCommand('PutObject', $params), self::UPLOAD_EXPIRES);
        // A assinatura cobre a chave e a cifragem KMS, não o tipo: o formato que vale é o da extensão, que o servidor escolhe.
        $headers = ['Content-Type' => $mime];

        foreach ($request->getHeaders() as $name => $values) {
            if (str_starts_with(strtolower($name), 'x-amz-')) {
                $headers[$name] = implode(',', $values);
            }
        }

        return ['url' => (string) $request->getUri(), 'headers' => $headers];
    }

    /**
     * Os trechos que chegaram de fato, em ordem. Um objeto maior que o limite é ignorado: a URL assinada não limita o
     * tamanho.
     *
     * @return list<array{key: string, sequence: int, start_ms: int, duration_ms: int, format: string}>
     */
    public function listSegments(string $uuid): array
    {
        $segments = [];

        foreach ($this->listKeys(config('session_transcription.s3.audio_prefix').'/'.$uuid.'/', withSize: true) as $key => $size) {
            if ($size > (int) config('session_transcription.segment_max_bytes') || ! preg_match(self::SEGMENT_PATTERN, $key, $match)) {
                continue;
            }

            $segments[] = [
                'key' => $key,
                'sequence' => (int) $match[1],
                'start_ms' => (int) $match[2],
                'duration_ms' => (int) $match[3],
                'format' => $match[4],
            ];
        }

        usort($segments, fn (array $a, array $b) => $a['sequence'] <=> $b['sequence']);

        return $segments;
    }

    /**
     * Inicia a transcrição de um trecho. Repetir não duplica: o nome do job é fixo por trecho.
     *
     * @param  array{key: string, sequence: int, format: string}  $segment
     */
    public function startJob(string $uuid, array $segment): void
    {
        $params = [
            'TranscriptionJobName' => self::jobName($uuid, $segment['sequence']),
            'LanguageCode' => config('session_transcription.language'),
            'MediaFormat' => $segment['format'],
            'Media' => ['MediaFileUri' => 's3://'.$this->bucket().'/'.$segment['key']],
            'OutputBucketName' => $this->bucket(),
            'OutputKey' => self::outputKey($uuid, $segment['sequence']),
            // Canal esquerdo: microfone da psicóloga. Direito: convidados.
            'Settings' => ['ChannelIdentification' => true],
        ];

        if ($kms = config('session_transcription.s3.kms_key_id')) {
            $params['OutputEncryptionKMSKeyId'] = $kms;
        }

        try {
            $this->transcribeClient()->startTranscriptionJob($params);
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() === 'ConflictException') {
                return;
            }

            $this->registerFailure($e, 'startTranscriptionJob');

            throw $e;
        }
    }

    /**
     * @return 'QUEUED'|'IN_PROGRESS'|'COMPLETED'|'FAILED'
     */
    public function jobStatus(string $uuid, int $sequence): string
    {
        try {
            $result = $this->transcribeClient()->getTranscriptionJob(['TranscriptionJobName' => self::jobName($uuid, $sequence)]);
        } catch (AwsException $e) {
            $this->registerFailure($e, 'getTranscriptionJob');

            throw $e;
        }

        return (string) $result['TranscriptionJob']['TranscriptionJobStatus'];
    }

    /**
     * O JSON do Transcribe para um trecho, ou null se não existir.
     */
    public function readOutput(string $uuid, int $sequence): ?array
    {
        try {
            $result = $this->s3Client('worker')->getObject(['Bucket' => $this->bucket(), 'Key' => self::outputKey($uuid, $sequence)]);
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() === 'NoSuchKey') {
                return null;
            }

            $this->registerFailure($e, 'getObject');

            throw $e;
        }

        $json = json_decode((string) $result['Body'], true);

        return is_array($json) ? $json : null;
    }

    /**
     * Apaga o áudio, a saída e os jobs da sessão. Cada parte é tentada mesmo se outra falhar; a primeira falha é relançada
     * no fim, para quem chamou registrar e o ciclo de vida do bucket cobrir o que sobrou.
     */
    public function removeSession(string $uuid, int $maxSequence): void
    {
        $failure = null;

        foreach ([config('session_transcription.s3.audio_prefix'), config('session_transcription.s3.text_prefix')] as $prefix) {
            try {
                $keys = array_keys($this->listKeys($prefix.'/'.$uuid.'/'));

                foreach (array_chunk($keys, 1000) as $chunk) {
                    $this->s3Client('worker')->deleteObjects([
                        'Bucket' => $this->bucket(),
                        'Delete' => ['Objects' => array_map(fn (string $key) => ['Key' => $key], $chunk), 'Quiet' => true],
                    ]);
                }
            } catch (AwsException $e) {
                $this->registerFailure($e, 'deleteObjects');
                $failure ??= $e;
            }
        }

        for ($sequence = 0; $sequence <= $maxSequence; $sequence++) {
            try {
                $this->transcribeClient()->deleteTranscriptionJob(['TranscriptionJobName' => self::jobName($uuid, $sequence)]);
            } catch (AwsException $e) {
                if ($e->getAwsErrorCode() !== 'BadRequestException' && $e->getAwsErrorCode() !== 'NotFoundException') {
                    $this->registerFailure($e, 'deleteTranscriptionJob');
                    $failure ??= $e;
                }
            }
        }

        if ($failure) {
            throw $failure;
        }
    }

    public static function segmentKey(string $uuid, int $sequence, int $startMs, int $durationMs, string $extension): string
    {
        return sprintf('%s/%s/%04d-%d-%d.%s', config('session_transcription.s3.audio_prefix'), $uuid, $sequence, $startMs, $durationMs, $extension);
    }

    private static function outputKey(string $uuid, int $sequence): string
    {
        return sprintf('%s/%s/%04d.json', config('session_transcription.s3.text_prefix'), $uuid, $sequence);
    }

    private static function jobName(string $uuid, int $sequence): string
    {
        return sprintf('psi4-%s-%04d', $uuid, $sequence);
    }

    /**
     * @return array<string, int>
     */
    private function listKeys(string $prefix, bool $withSize = false): array
    {
        $keys = [];

        try {
            $pages = $this->s3Client('worker')->getPaginator('ListObjectsV2', ['Bucket' => $this->bucket(), 'Prefix' => $prefix]);

            foreach ($pages as $page) {
                foreach ($page['Contents'] ?? [] as $object) {
                    $keys[(string) $object['Key']] = $withSize ? (int) $object['Size'] : 0;
                }
            }
        } catch (AwsException $e) {
            $this->registerFailure($e, 'listObjectsV2');

            throw $e;
        }

        return $keys;
    }

    private function bucket(): string
    {
        return (string) config('session_transcription.s3.bucket');
    }

    private function s3Client(string $role): S3Client
    {
        return new S3Client([
            'version' => 'latest',
            'region' => (string) config('session_transcription.aws.region'),
            'credentials' => $this->credentials($role),
        ]);
    }

    private function transcribeClient(): TranscribeServiceClient
    {
        return new TranscribeServiceClient([
            'version' => 'latest',
            'region' => (string) config('session_transcription.aws.region'),
            'credentials' => $this->credentials('worker'),
        ]);
    }

    /**
     * @return array{key: string, secret: string}
     */
    private function credentials(string $role): array
    {
        $key = (string) config('session_transcription.aws.'.$role.'.key');
        $secret = (string) config('session_transcription.aws.'.$role.'.secret');

        if ($key === '' || $secret === '' || $this->bucket() === '') {
            RegisterLog::dispatchSync([
                'message' => 'Credencial ou bucket da transcrição não configurado',
                'data' => ['role' => $role],
                'method' => 'AwsTranscribeWrapper->credentials',
            ]);

            throw new \DomainException(__('session-transcriptions.unavailable'));
        }

        return ['key' => $key, 'secret' => $secret];
    }

    private function registerFailure(AwsException $e, string $operation): void
    {
        RegisterLog::dispatchSync([
            'message' => 'Falha na AWS da transcrição ('.$operation.'): '.($e->getAwsErrorCode() ?? 'sem código'),
            'data' => ['status' => $e->getStatusCode(), 'code' => $e->getAwsErrorCode(), 'request_id' => $e->getAwsRequestId()],
            'method' => 'AwsTranscribeWrapper->'.$operation,
        ]);
    }
}
