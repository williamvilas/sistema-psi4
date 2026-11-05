<?php

declare(strict_types=1);

namespace App\Helpers;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class HolidayHelper
{
    /**
     * Feriados nacionais (fixos e móveis) a partir de $from até o fim do ano seguinte.
     *
     * @return array<int, array{date: string, name: string}>
     */
    public static function national(?CarbonInterface $from = null): array
    {
        $from = ($from ?? now())->copy()->startOfDay();
        $holidays = [];

        foreach ([$from->year, $from->year + 1] as $year) {
            foreach (self::byYear($year) as $date => $name) {
                if (Carbon::parse($date)->gte($from)) {
                    $holidays[] = ['date' => $date, 'name' => $name];
                }
            }
        }

        return $holidays;
    }

    public static function isNational(CarbonInterface $date): bool
    {
        return array_key_exists($date->toDateString(), self::byYear($date->year));
    }

    /**
     * @return array<string, string> data (Y-m-d) => nome
     */
    private static function byYear(int $year): array
    {
        $easter = self::easter($year);

        $holidays = [
            "{$year}-01-01" => 'Confraternização Universal',
            $easter->copy()->subDays(47)->toDateString() => 'Carnaval',
            $easter->copy()->subDays(2)->toDateString() => 'Sexta-feira Santa',
            "{$year}-04-21" => 'Tiradentes',
            "{$year}-05-01" => 'Dia do Trabalho',
            $easter->copy()->addDays(60)->toDateString() => 'Corpus Christi',
            "{$year}-09-07" => 'Independência do Brasil',
            "{$year}-10-12" => 'Nossa Senhora Aparecida',
            "{$year}-11-02" => 'Finados',
            "{$year}-11-15" => 'Proclamação da República',
            "{$year}-11-20" => 'Consciência Negra',
            "{$year}-12-25" => 'Natal',
        ];

        ksort($holidays);

        return $holidays;
    }

    /**
     * Domingo de Páscoa (algoritmo de Meeus/Jones/Butcher).
     */
    private static function easter(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day)->startOfDay();
    }
}
