<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * O estado da transcrição não permite mais a operação (ex.: o paciente revogou). O Jitsi para ao receber o 409.
 */
class SessionTranscriptionConflictException extends \DomainException
{
}
