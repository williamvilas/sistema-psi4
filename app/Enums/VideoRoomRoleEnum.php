<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum VideoRoomRoleEnum: string
{
    use EnumTrait;

    case MODERATOR = 'moderator';
    case GUEST = 'guest';
}
