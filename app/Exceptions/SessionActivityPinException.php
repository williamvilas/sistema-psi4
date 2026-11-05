<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * PIN de registro errado ou bloqueado, ao gravar ou retomar uma dinâmica da sessão de vídeo.
 */
class SessionActivityPinException extends \DomainException
{
    public function __construct(string $message, public readonly bool $locked = false)
    {
        parent::__construct($message);
    }
}
