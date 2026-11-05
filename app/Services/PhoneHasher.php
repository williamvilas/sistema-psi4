<?php

declare(strict_types=1);

namespace App\Services;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Normaliza telefones para E.164 e gera o HMAC usado para identificar o participante da sala de vídeo.
 * O telefone em claro nunca é persistido nem logado, só o HMAC.
 */
class PhoneHasher
{
    private const string DEFAULT_REGION = 'BR';

    public function normalize(string $raw): ?string
    {
        $util = PhoneNumberUtil::getInstance();
        $raw = trim($raw);

        // "5511999990000" sem o "+" seria lido como número local; com 12/13 dígitos iniciando em 55 é o DDI do Brasil
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if (! str_starts_with($raw, '+') && str_starts_with($digits, '55') && in_array(strlen($digits), [12, 13], true)) {
            $raw = '+'.$digits;
        }

        try {
            $number = $util->parse($raw, self::DEFAULT_REGION);
        } catch (NumberParseException) {
            return null;
        }

        if (! $util->isValidNumber($number)) {
            return null;
        }

        return $util->format($number, PhoneNumberFormat::E164);
    }

    public function hmac(string $e164): string
    {
        return hash_hmac('sha256', $e164, (string) config('psi_video.phone_pepper'));
    }
}
