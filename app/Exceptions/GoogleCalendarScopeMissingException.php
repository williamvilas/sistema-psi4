<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * O psicólogo concluiu o OAuth do Google sem autorizar o escopo obrigatório
 * da agenda (as permissões vêm desmarcadas por padrão na tela de consentimento).
 */
class GoogleCalendarScopeMissingException extends \DomainException
{
}
