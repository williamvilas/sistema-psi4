<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum DocumentFrequencyEnum: string
{
    use EnumTrait;

    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case SEMIANNUAL = 'semiannual';
    case ANNUAL = 'annual';

    public function order(): int
    {
        return match ($this) {
            self::DAILY => 1,
            self::WEEKLY => 2,
            self::MONTHLY => 3,
            self::QUARTERLY => 4,
            self::SEMIANNUAL => 5,
            self::ANNUAL => 6,
        };
    }

    public function label(): string
    {
        return __('documents.frequency.'.$this->value);
    }

    /** Forma flexionada para uso no corpo do documento (ex.: "com atendimentos semanais"). */
    public function adjective(): string
    {
        return __('documents.frequency_adjective.'.$this->value);
    }

    public static function getTexts(): array
    {
        $texts = [];

        foreach (self::sortedCases() as $case) {
            $texts[$case->value] = $case->label();
        }

        return $texts;
    }
}
