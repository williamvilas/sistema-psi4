<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\Carbon;

enum BirthdayCelebrantsEnum: string
{
    case TODAY = 'today';
    case TOMORROW = 'tomorrow';
    case THIS_WEEK = 'this_week';

    public static function options(string $timezone): array
    {
        $now = Carbon::now($timezone);

        // Hoje
        $todayText = $now->format('d/m');
        $todayValue = $now->format('m-d');

        // Amanhã
        $tomorrow = $now->copy()->addDay();

        // Semana (domingo → sábado)
        $startWeek = $now->copy()->startOfWeek(Carbon::SUNDAY);
        $endWeek = $now->copy()->endOfWeek(Carbon::SATURDAY);

        return [
            self::TODAY->value => [
                'text' => $todayText,
                'value' => $todayValue,
            ],

            self::TOMORROW->value => [
                'text' => $tomorrow->format('d/m'),
                'value' => $tomorrow->format('m-d'),
            ],

            self::THIS_WEEK->value => [
                'text' => $startWeek->format('d/m').' até '.$endWeek->format('d/m'),
                'value' => $startWeek->format('m-d').'|'.$endWeek->format('m-d'),
            ],
        ];
    }
}
