<?php

declare(strict_types=1);

namespace App\Traits;

use Carbon\Carbon;

trait ReportPeriodTrait
{
    /**
     * @return array{0: Carbon, 1: Carbon} início e fim do período no fuso informado
     */
    private function period(string $timezone, string $startDate, string $endDate): array
    {
        return [
            Carbon::createFromFormat('Y-m-d', $startDate, $timezone)->startOfDay(),
            Carbon::createFromFormat('Y-m-d', $endDate, $timezone)->endOfDay(),
        ];
    }

    /**
     * Período imediatamente anterior, com a mesma quantidade de dias.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function previousPeriod(Carbon $start, Carbon $end): array
    {
        return [
            $start->copy()->subDays($this->days($start, $end))->startOfDay(),
            $start->copy()->subDay()->endOfDay(),
        ];
    }

    /**
     * Variação percentual; nula quando o período anterior não tem valor para comparar.
     */
    private function variation(float $current, float $previous): ?float
    {
        return $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null;
    }

    private function days(Carbon $start, Carbon $end): int
    {
        return (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;
    }
}
