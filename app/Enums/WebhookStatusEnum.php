<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum WebhookStatusEnum: string
{
    use EnumTrait;

    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case ERROR = 'error';
    case DONE = 'done';

}
