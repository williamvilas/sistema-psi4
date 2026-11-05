<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum PlanCycleEnum: string
{
    use EnumTrait;

    case MONTHLY = 'monthly';
    case ANNUAL = 'annual';
}
