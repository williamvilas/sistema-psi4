<?php

declare(strict_types=1);

namespace App\Helpers;

use Carbon\Carbon;

class DateTimeHelper
{
    public static function convertToDatabase(?string $dateInput = null, string $format = 'Y-m-d H:i:s'): ?string
    {
        if (! $dateInput) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('d/m/Y H:i:s', $dateInput);
        } catch (\Exception $e1) {
            try {
                $date = Carbon::createFromFormat('d/m/Y', $dateInput)->startOfDay();
            } catch (\Exception $e2) {
                return null;
            }
        }

        return $date->format($format);
    }
}
