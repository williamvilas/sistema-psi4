<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * CPF ou telefone inválido, sala inexistente/cancelada ou dado fora da lista da sala.
 * Todos os casos respondem o mesmo 403 para não revelar se a sala existe.
 */
class VideoRoomAccessDeniedException extends \DomainException
{
}
