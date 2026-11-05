<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\PublicKeyEnvelope;
use App\Helpers\SessionTranscriptHelper;
use Tests\TestCase;

class SessionTranscriptHelperTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function word(string $content, float $start): array
    {
        return ['type' => 'pronunciation', 'start_time' => (string) $start, 'end_time' => (string) ($start + 0.5), 'alternatives' => [['content' => $content]]];
    }

    public function testItSplitsSpeechByPauseAndKeepsChannels(): void
    {
        $output = ['results' => ['channel_labels' => ['channels' => [
            ['channel_label' => 'ch_0', 'items' => [
                self::word('Bom', 0.0),
                self::word('dia', 0.6),
                ['type' => 'punctuation', 'alternatives' => [['content' => '.']]],
                self::word('Vamos', 5.0),
            ]],
            ['channel_label' => 'ch_1', 'items' => [self::word('Oi', 2.0)]],
            ['channel_label' => 'ch_2', 'items' => [self::word('Ruído', 2.5)]],
        ]]]];

        $segments = SessionTranscriptHelper::segments($output, 60000);

        $this->assertSame([
            ['channel' => 0, 'startMs' => 60000, 'endMs' => 61100, 'text' => 'Bom dia.'],
            ['channel' => 1, 'startMs' => 62000, 'endMs' => 62500, 'text' => 'Oi'],
            ['channel' => 0, 'startMs' => 65000, 'endMs' => 65500, 'text' => 'Vamos'],
        ], $segments);
    }

    public function testItIgnoresOutputWithoutChannels(): void
    {
        $this->assertSame([], SessionTranscriptHelper::segments(['results' => []], 0));
    }

    public function testItFindsGapsOnlyAboveTheThreshold(): void
    {
        $gaps = SessionTranscriptHelper::gaps([
            ['start_ms' => 0, 'duration_ms' => 120000],
            ['start_ms' => 122000, 'duration_ms' => 120000],
            ['start_ms' => 300000, 'duration_ms' => 60000],
        ]);

        $this->assertSame([['startMs' => 242000, 'endMs' => 300000]], $gaps);
    }

    public function testItFormatsSpeechAndGapsInTimeOrder(): void
    {
        $text = SessionTranscriptHelper::format(
            [['channel' => 0, 'startMs' => 1000, 'text' => 'Como foi?'], ['channel' => 1, 'startMs' => 200000, 'text' => 'Bem.']],
            [['startMs' => 60000, 'endMs' => 150000]],
        );

        $this->assertSame("[00:01] Psicóloga: Como foi?\n[01:00] (trecho não gravado: 2 min)\n[03:20] Paciente: Bem.", $text);
    }

    public function testItSealsOnlyForTheOwnerOfThePrivateKey(): void
    {
        $owner = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $public = str_replace(['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n"], '', openssl_pkey_get_details($owner)['key']);

        $sealed = PublicKeyEnvelope::seal('{"texto":"sigiloso"}', $public, 'PSN', 1, 'psi4-session-note', 'psi4-session-note|abc');

        $this->assertSame("PSN\x01\x04", substr($sealed, 0, 5));
        $this->assertStringNotContainsString('sigiloso', $sealed);
        $this->assertSame('{"texto":"sigiloso"}', self::open($sealed, $owner, 'psi4-session-note|abc'));
        $this->assertFalse(self::open($sealed, $owner, 'psi4-session-note|outra'));
    }

    public function testItRejectsInvalidPublicKey(): void
    {
        $this->expectException(\RuntimeException::class);

        PublicKeyEnvelope::seal('x', 'chave-invalida', 'PSN', 1, 'psi4-session-note', 'aad');
    }

    private static function open(string $sealed, \OpenSSLAsymmetricKey $owner, string $aad): string|false
    {
        $ephemeral = substr($sealed, 4, 65);
        $spki = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$ephemeral;
        $sender = openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64, "\n")."-----END PUBLIC KEY-----\n");
        $key = hash_hkdf('sha256', openssl_pkey_derive($sender, $owner, 32), 32, 'psi4-session-note', $ephemeral);
        $rest = substr($sealed, 81);

        return openssl_decrypt(substr($rest, 0, -16), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($sealed, 69, 12), substr($rest, -16), $aad);
    }
}
