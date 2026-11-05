<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum InvoicesStatusEnum: string
{
    use EnumTrait;

    case PENDING = 'pending';
    case CANCEL = 'cancel';
    case CANCELED = 'canceled';
    case DONE = 'done';
}
