<?php

declare(strict_types=1);

namespace Tests\Unit\Wrappers;

use App\Jobs\RegisterLog;
use App\Wrappers\AwsTranscribeWrapper;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AwsTranscribeWrapperTest extends TestCase
{
    private const string SECRET = 'segredo-de-teste';

    private const string UUID = '7a1c7a7e-3d55-4a44-9c11-6a2b8e0d1f44';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'session_transcription.s3.bucket' => 'psi-transcricao-teste',
            'session_transcription.s3.kms_key_id' => 'arn:aws:kms:sa-east-1:111122223333:key/teste',
            'session_transcription.aws.region' => 'sa-east-1',
            'session_transcription.aws.uploader' => ['key' => 'AKIAUPLOADER', 'secret' => self::SECRET],
        ]);
    }

    public function testItSignsPutOnlyForTheSegmentObjectWithKms(): void
    {
        $upload = (new AwsTranscribeWrapper)->segmentUploadUrl(self::UUID, 3, 360000, 120000, 'audio/webm;codecs=opus', 'webm');
        parse_str((string) parse_url($upload['url'], PHP_URL_QUERY), $query);

        $this->assertStringContainsString('psi-transcricao-teste', $upload['url']);
        $this->assertStringContainsString('transcription-audio/'.self::UUID.'/0003-360000-120000.webm', $upload['url']);
        $this->assertSame('300', $query['X-Amz-Expires']);
        $this->assertStringStartsWith('AKIAUPLOADER/', $query['X-Amz-Credential']);
        // Sem os cabeçalhos KMS assinados, o S3 recusa o envio: o áudio nunca fica sem a chave do bucket.
        $this->assertStringContainsString('x-amz-server-side-encryption-aws-kms-key-id', $query['X-Amz-SignedHeaders']);
        $this->assertStringNotContainsString(self::SECRET, $upload['url']);

        $headers = array_change_key_case($upload['headers']);

        $this->assertSame('audio/webm;codecs=opus', $headers['content-type']);
        $this->assertSame('aws:kms', $headers['x-amz-server-side-encryption']);
        $this->assertSame('arn:aws:kms:sa-east-1:111122223333:key/teste', $headers['x-amz-server-side-encryption-aws-kms-key-id']);
    }

    public function testItBuildsTheSegmentKeyOnTheServer(): void
    {
        $this->assertSame(
            'transcription-audio/'.self::UUID.'/0012-1440000-95000.mp4',
            AwsTranscribeWrapper::segmentKey(self::UUID, 12, 1440000, 95000, 'mp4'),
        );
    }

    public function testItThrowsAndLogsWithoutCredentials(): void
    {
        Bus::fake([RegisterLog::class]);
        config(['session_transcription.aws.uploader.secret' => '']);

        try {
            (new AwsTranscribeWrapper)->segmentUploadUrl(self::UUID, 0, 0, 120000, 'audio/mp4', 'mp4');
            $this->fail('Deveria ter lançado DomainException.');
        } catch (\DomainException $e) {
            $this->assertSame(__('session-transcriptions.unavailable'), $e->getMessage());
        }

        Bus::assertDispatchedSync(RegisterLog::class);
    }
}
