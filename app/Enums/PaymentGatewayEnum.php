<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum PaymentGatewayEnum: string
{
    use EnumTrait;

    case MERCADO_PAGO = 'mercado_pago';

    public static function getTexts(): array
    {
        $text = [];

        foreach (self::values() as $value) {
            $text[$value] = __('enum.'.$value);
        }

        asort($text);

        return $text;
    }
}
