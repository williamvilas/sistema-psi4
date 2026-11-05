<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum GoogleCalendarStatusEnum: string
{
    use EnumTrait;

    case PENDING = 'pending';
    case INTEGRATED = 'integrated';
    case NEEDS_REAUTH = 'needs_reauth';

    public function getText(): string
    {
        return __('enum.google_calendar_status.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::PENDING => 'fas fa-clock',
            self::INTEGRATED => 'fas fa-check-circle',
            self::NEEDS_REAUTH => 'fas fa-triangle-exclamation',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'text-warning',
            self::INTEGRATED => 'text-success',
            self::NEEDS_REAUTH => 'text-warning',
        };
    }
}
