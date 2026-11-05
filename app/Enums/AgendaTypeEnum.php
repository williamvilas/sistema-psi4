<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum AgendaTypeEnum: string
{
    use EnumTrait;

    case ONLINE = 'online';
    case IN_PERSON = 'in_person';

    public function getText(): string
    {
        return __('agenda.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::ONLINE => 'fas fa-video',
            self::IN_PERSON => 'fas fa-location-dot',
        };
    }
}
