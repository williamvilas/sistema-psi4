<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum HealthReceiptStatusEnum: string
{
    use EnumTrait;

    case PENDING = 'pending';
    case EXPORTED = 'exported';
    case CANCELLED = 'cancelled';

    public function getText(): string
    {
        return __('enum.health_receipt_status.'.$this->value);
    }

    /**
     * Tom do chip de status (classes `dash-chip-*` de components/css-dash).
     */
    public function tone(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::EXPORTED => 'success',
            self::CANCELLED => 'muted',
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
