<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum HealthReceiptSourceEnum: string
{
    use EnumTrait;

    case PAYMENT_ORDER = 'payment_order';
    case MANUAL = 'manual';

    public function getText(): string
    {
        return __('enum.health_receipt_source.'.$this->value);
    }
}
