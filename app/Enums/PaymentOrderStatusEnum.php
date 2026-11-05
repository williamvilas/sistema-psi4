<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum PaymentOrderStatusEnum: string
{
    use EnumTrait;

    case PENDING = 'pending';
    case PAID = 'paid';
    case PROCESSING = 'processing';
    case CANCELLED = 'cancelled';
    case REVERSING = 'reversing';
    case REFUNDED = 'refunded';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';

    public function getText(): string
    {
        return __('enum.payment_order_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'bg-warning',
            self::PAID => 'bg-success',
            self::PROCESSING => 'bg-primary',
            self::CANCELLED => 'bg-secondary',
            self::REVERSING => 'bg-info',
            self::REFUNDED => 'bg-info',
            self::REJECTED => 'bg-danger',
            self::EXPIRED => 'bg-dark',
        };
    }

    /**
     * Tom do chip de status (classes `dash-chip-*` de components/css-dash).
     */
    public function tone(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::PAID => 'success',
            self::PROCESSING, self::REVERSING, self::REFUNDED => 'info',
            self::CANCELLED => 'muted',
            self::REJECTED, self::EXPIRED => 'danger',
        };
    }

    public static function getTexts(): array
    {
        $texts = [];

        foreach (self::cases() as $case) {
            $texts[$case->value] = $case->getText();
        }

        return $texts;
    }
}
