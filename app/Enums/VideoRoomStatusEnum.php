<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum VideoRoomStatusEnum: string
{
    use EnumTrait;

    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';
}
