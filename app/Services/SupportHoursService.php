<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\HolidayHelper;
use Carbon\CarbonInterface;

/**
 * Horário de atendimento do suporte (config('info.support_hours')), exibido na tela de Ajuda.
 */
class SupportHoursService
{
    /** Limite de dias procurados para a próxima abertura (cobre feriados emendados). */
    private const MAX_DAYS_AHEAD = 14;

    /**
     * @return array{online: bool, label: string}
     */
    public function status(?CarbonInterface $now = null): array
    {
        $now = ($now ?? now())->copy()->setTimezone($this->timezone());
        $today = $this->hoursOn($now);

        if ($today && $now->gte($today[0]) && $now->lt($today[1])) {
            return ['online' => true, 'label' => __('help.status.online')];
        }

        for ($i = 0; $i <= self::MAX_DAYS_AHEAD; $i++) {
            $hours = $this->hoursOn($now->copy()->addDays($i));

            if ($hours && $hours[0]->gt($now)) {
                return [
                    'online' => false,
                    'label' => __('help.status.offline', ['when' => $this->when($i, $hours[0]), 'hour' => $this->hour($hours[0])]),
                ];
            }
        }

        return ['online' => false, 'label' => __('help.status.closed')];
    }

    /**
     * Grade semanal agrupando dias seguidos com o mesmo horário (ex.: "Segunda a sexta · 8h às 20h").
     *
     * @return array<int, array{days: string, hours: string}>
     */
    public function schedule(): array
    {
        $days = config('info.support_hours.days', []);
        $groups = [];

        foreach (range(1, 7) as $day) {
            $hours = $days[$day] ?? null;
            $last = array_key_last($groups);

            if ($last !== null && $groups[$last]['hours'] === $hours) {
                $groups[$last]['to'] = $day;

                continue;
            }

            $groups[] = ['from' => $day, 'to' => $day, 'hours' => $hours];
        }

        $rows = array_map(fn (array $group) => [
            'days' => $group['from'] === $group['to']
                ? __("help.days.{$group['from']}")
                : __('help.days_range', ['from' => __("help.days.{$group['from']}"), 'to' => mb_strtolower(__("help.days.{$group['to']}"))]),
            'hours' => $group['hours']
                ? __('help.hours_range', ['open' => $this->hour($group['hours'][0]), 'close' => $this->hour($group['hours'][1])])
                : __('help.closed'),
        ], $groups);

        $rows[] = ['days' => __('help.holidays'), 'hours' => __('help.closed')];

        return $rows;
    }

    /**
     * Abertura e fechamento do dia informado, ou null quando fechado (dia sem expediente ou feriado nacional).
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}|null
     */
    private function hoursOn(CarbonInterface $date): ?array
    {
        $hours = config('info.support_hours.days', [])[$date->dayOfWeekIso] ?? null;

        if (! $hours || HolidayHelper::isNational($date)) {
            return null;
        }

        return [
            $date->copy()->setTimeFromTimeString($hours[0]),
            $date->copy()->setTimeFromTimeString($hours[1]),
        ];
    }

    private function when(int $daysAhead, CarbonInterface $date): string
    {
        return match ($daysAhead) {
            0 => __('help.when.today'),
            1 => __('help.when.tomorrow'),
            default => __("help.when.weekdays.{$date->dayOfWeekIso}"),
        };
    }

    private function hour(CarbonInterface|string $time): string
    {
        [$hour, $minute] = array_map('intval', explode(':', is_string($time) ? $time : $time->format('H:i')));

        return $minute === 0 ? "{$hour}h" : sprintf('%dh%02d', $hour, $minute);
    }

    private function timezone(): string
    {
        return config('info.support_hours.timezone', 'America/Sao_Paulo');
    }
}
