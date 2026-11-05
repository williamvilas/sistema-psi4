<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum NotificationRecipientEnum: string
{
    use EnumTrait;

    case PATIENT = 'patient';
    case PSYCHOLOGIST = 'psychologist';

    /**
     * @return self[] destinatários da notificação de acordo com o novo status da agenda
     */
    public static function forAgendaStatus(AgendaStatusEnum $status): array
    {
        // A psicóloga só é avisada dos agendamentos feitos pela página pública (PublicBookingService),
        // não dos que ela mesma cria: os avisos de status são só para o paciente
        return [self::PATIENT];
    }
}
