<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Cifra com a chave pública da psicóloga (`scale_public_key`), no mesmo envelope que o Jitsi usa para dinâmicas e escalas:
 * magia (3 bytes) + versão (1 byte) + pública efêmera P-256 sem compressão (65 bytes) + iv (12 bytes) + cifra com tag.
 * A chave vem de ECDH entre a efêmera e a pública dela, por HKDF-SHA256 com a efêmera como sal. Só o navegador dela, com a
 * senha mestre, abre (`crypto.js`); aqui não há como decifrar.
 */
class PublicKeyEnvelope
{
    private const int IV_LENGTH = 12;

    private const int TAG_LENGTH = 16;

    private const int COORDINATE_LENGTH = 32;

    public static function seal(string $plain, string $publicKeyBase64, string $magic, int $version, string $info, string $aad): string
    {
        $recipient = openssl_pkey_get_public(self::pem($publicKeyBase64));
        $ephemeral = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);

        if ($recipient === false || $ephemeral === false) {
            throw new \RuntimeException('Chave pública inválida.');
        }

        $secret = openssl_pkey_derive($recipient, $ephemeral, self::COORDINATE_LENGTH);
        $details = openssl_pkey_get_details($ephemeral);

        if ($secret === false || $details === false) {
            throw new \RuntimeException('Falha ao derivar a chave.');
        }

        $ephemeralPublic = "\x04".self::pad($details['ec']['x']).self::pad($details['ec']['y']);
        $key = hash_hkdf('sha256', $secret, 32, $info, $ephemeralPublic);
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad, self::TAG_LENGTH);

        if ($cipher === false) {
            throw new \RuntimeException('Falha ao cifrar.');
        }

        return $magic.chr($version).$ephemeralPublic.$iv.$cipher.$tag;
    }

    private static function pem(string $publicKeyBase64): string
    {
        return "-----BEGIN PUBLIC KEY-----\n".chunk_split($publicKeyBase64, 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private static function pad(string $coordinate): string
    {
        return str_pad($coordinate, self::COORDINATE_LENGTH, "\0", STR_PAD_LEFT);
    }
}
