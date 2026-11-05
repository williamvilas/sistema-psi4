<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Participante autorizado, mas fora da janela de acesso da sala (antes de abrir ou depois de fechar).
 * O controller responde 410 com a mensagem amigável.
 */
class VideoRoomOutsideWindowException extends \DomainException
{
}
