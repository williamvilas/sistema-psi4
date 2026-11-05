<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum PlanUserEnum: string
{
    use EnumTrait;

    case FREE = 'free';
    case PROFESSIONAL = 'professional';
}
