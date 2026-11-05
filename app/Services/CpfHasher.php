<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\NumberHelper;
use App\Models\HealthReceipt;

/**
 * Normaliza o CPF e gera o HMAC usado para identificar o participante da sala de vídeo.
 * O CPF em claro nunca é gravado na sala nem logado, só o HMAC.
 */
class CpfHasher
{
    public function normalize(string $raw): ?string
    {
        $digits = NumberHelper::onlyNumbers($raw);

        return HealthReceipt::isValidCpf($digits) ? $digits : null;
    }

    public function hmac(string $cpf): string
    {
        return hash_hmac('sha256', $cpf, (string) config('psi_video.cpf_pepper'));
    }
}
