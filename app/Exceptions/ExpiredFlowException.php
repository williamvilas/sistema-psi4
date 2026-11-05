<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Fluxo público expirado (desafio do 2FA, link de recuperação/redefinição).
 * O controller responde 410 com o link do login para o frontend avisar e redirecionar.
 */
class ExpiredFlowException extends \DomainException
{
}
