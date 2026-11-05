<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum AgendaStatusEnum: string
{
    use EnumTrait;

    case SCHEDULED = 'scheduled';
    case CONFIRMED = 'confirmed';
    case NO_SHOW = 'no_show';
    case CANCELED = 'canceled';
    case COMPLETED = 'completed';

    /**
     * Tom do chip de status (classes `dash-chip-*` de components/css-dash).
     */
    public function tone(): string
    {
        return match ($this) {
            self::SCHEDULED => 'warning',
            self::CONFIRMED => 'info',
            self::COMPLETED => 'success',
            self::NO_SHOW => 'danger',
            self::CANCELED => 'muted',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::SCHEDULED, self::CONFIRMED], true);
    }
}
