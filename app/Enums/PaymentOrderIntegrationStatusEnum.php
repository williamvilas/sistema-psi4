<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum PaymentOrderIntegrationStatusEnum: string
{
    use EnumTrait;

    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case INTEGRATED = 'integrated';
    case ERROR = 'error';

    public function getText(): string
    {
        return __('enum.payment_order_integration_status.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::PENDING => 'fas fa-clock',
            self::PROCESSING => 'fas fa-spinner fa-spin',
            self::INTEGRATED => 'fas fa-check-circle',
            self::ERROR => 'fas fa-exclamation-circle',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'text-warning',
            self::PROCESSING => 'text-primary',
            self::INTEGRATED => 'text-success',
            self::ERROR => 'text-danger',
        };
    }
}
