<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Limite mensal do plano atingido (PlanLimitService).
 * O controller responde 403 com o link da tela de planos para o frontend oferecer o upgrade.
 */
class PlanLimitException extends \DomainException
{
}
