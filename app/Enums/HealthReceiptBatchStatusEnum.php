<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum HealthReceiptBatchStatusEnum: string
{
    use EnumTrait;

    case GENERATED = 'generated';
    case IMPORTED = 'imported';
    case UNDONE = 'undone';

    public function getText(): string
    {
        return __('enum.health_receipt_batch_status.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::GENERATED => 'warning',
            self::IMPORTED => 'success',
            self::UNDONE => 'muted',
        };
    }
}
