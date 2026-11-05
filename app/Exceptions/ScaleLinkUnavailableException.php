<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Link de escala inexistente, expirado, respondido, cancelado ou de paciente removido.
 * O controller público responde 410 (a página não oferece "tentar novamente").
 */
class ScaleLinkUnavailableException extends \DomainException
{
}
