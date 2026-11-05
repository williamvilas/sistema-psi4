<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum NotificationTypeEnum: string
{
    use EnumTrait;

    case AGENDA_SCHEDULED = 'agenda_scheduled';
    case AGENDA_CANCELED = 'agenda_canceled';
    case AGENDA_NO_SHOW = 'agenda_no_show';
    case AGENDA_COMPLETED = 'agenda_completed';
    case AGENDA_MEET_MISSING = 'agenda_meet_missing';
    case AGENDA_ATTENDANCE_PENDING = 'agenda_attendance';
    case AGENDA_REMINDER = 'agenda_reminder';
    case AGENDA_ROOM_LINK = 'agenda_room_link';
    case AGENDA_RESCHEDULE_REQUEST = 'agenda_reschedule';

    public function getText(): string
    {
        return __('enum.notification_type.'.$this->value);
    }

    /**
     * Status da agenda sem tipo (ex.: confirmed) não geram notificação.
     */
    public static function fromAgendaStatus(?AgendaStatusEnum $status): ?self
    {
        return match ($status) {
            AgendaStatusEnum::SCHEDULED => self::AGENDA_SCHEDULED,
            AgendaStatusEnum::CANCELED => self::AGENDA_CANCELED,
            AgendaStatusEnum::NO_SHOW => self::AGENDA_NO_SHOW,
            AgendaStatusEnum::COMPLETED => self::AGENDA_COMPLETED,
            default => null,
        };
    }
}
