<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

/**
 * Modalidade de atendimento configurada pelo psicólogo (geral ou de um dia da semana).
 */
enum AttendanceModeEnum: string
{
    use EnumTrait;

    case ONLINE = 'online';
    case IN_PERSON = 'in_person';
    case BOTH = 'both';

    public function getText(): string
    {
        return __('agenda.attendance_mode.'.$this->value);
    }

    /**
     * Tipos de consulta (agenda.type) aceitos pela modalidade.
     *
     * @return string[]
     */
    public function types(): array
    {
        return match ($this) {
            self::ONLINE => [AgendaTypeEnum::ONLINE->value],
            self::IN_PERSON => [AgendaTypeEnum::IN_PERSON->value],
            self::BOTH => [AgendaTypeEnum::ONLINE->value, AgendaTypeEnum::IN_PERSON->value],
        };
    }

    public function hasInPerson(): bool
    {
        return $this !== self::ONLINE;
    }
}
