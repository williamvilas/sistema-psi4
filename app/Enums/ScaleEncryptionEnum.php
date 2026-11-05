<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

/**
 * Como o conteúdo de uma aplicação de escala foi cifrado no navegador.
 * - SYMMETRIC: aplicada pela psicóloga, com a chave dos prontuários (AES-GCM).
 * - ECDH: respondida pelo paciente por link, com a chave pública das escalas da psicóloga.
 */
enum ScaleEncryptionEnum: string
{
    use EnumTrait;

    case SYMMETRIC = 'symmetric';
    case ECDH = 'ecdh';
}
