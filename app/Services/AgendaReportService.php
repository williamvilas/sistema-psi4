<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AgendaStatusEnum;
use App\Excel\Exports\AttendanceReportExport;
use App\Models\Agenda;
use App\Models\AgendaSettings;
use App\Traits\ReportPeriodTrait;
use Carbon\Carbon;
use Illuminate\Support\Collection;

readonly class AgendaReportService
{
    use ReportPeriodTrait;

    private const TOP_LIMIT = 5;

    /** Paciente é destacado com pelo menos esta quantidade de faltas e esta taxa de faltas (%). */
    private const ATTENTION_MIN_NO_SHOW = 2;

    private const ATTENTION_RATE = 50.0;

    /** Faltas mínimas no período para destacar o dia da semana que as concentra. */
    private const INSIGHT_MIN_NO_SHOW = 3;

    /** Horários mínimos de um turno para ele entrar no destaque de menor ocupação. */
    private const INSIGHT_MIN_SLOTS = 4;

    /** Dias da semana de segunda a domingo (0 = domingo). */
    private const WEEK_ORDER = [1, 2, 3, 4, 5, 6, 0];

    public function __construct(
        private Agenda $agenda,
        private AgendaService $agendaService,
        private AgendaSettingService $agendaSettingService,
    ) {
    }

    /**
     * Faltas e cancelamentos. Agendamentos ainda abertos (agendado/confirmado) ficam fora das taxas e
     * são contados à parte em "pending" quando já passaram, para avisar que os números estão incompletos.
     */
    public function attendance(int $userId, string $timezone, string $startDate, string $endDate): array
    {
        [$start, $end] = $this->period($timezone, $startDate, $endDate);
        [$previousStart, $previousEnd] = $this->previousPeriod($start, $end);
        $startUtc = $start->copy()->utc();
        $endUtc = $end->copy()->utc();

        $current = $this->attendanceIndicators($userId, $startUtc, $endUtc);
        $previous = $this->attendanceIndicators($userId, $previousStart->copy()->utc(), $previousEnd->copy()->utc());

        $closed = $this->agenda->listByStatusesBetween(
            $userId,
            [AgendaStatusEnum::COMPLETED, AgendaStatusEnum::NO_SHOW, AgendaStatusEnum::CANCELED],
            $startUtc,
            $endUtc,
            ['scheduled_on', 'status'],
        );
        $chart = $this->weekdayChart($closed, $timezone);

        return [
            'total' => $current['total'],
            'attendance' => $this->rateIndicator($current, $previous, 'completed'),
            'no_show' => $this->rateIndicator($current, $previous, 'no_show'),
            'canceled' => $this->rateIndicator($current, $previous, 'canceled'),
            'lost_revenue' => [
                'total' => $current['lost_revenue'],
                'count' => $current['no_show'],
                'variation' => $this->variation($current['lost_revenue'], $previous['lost_revenue']),
            ],
            'pending' => $this->agenda->countPendingAttendance($userId, now()->utc(), $startUtc, $endUtc),
            'period' => ['start' => $start->format('d/m/Y'), 'end' => $end->format('d/m/Y')],
            'previous_period' => ['start' => $previousStart->format('d/m/Y'), 'end' => $previousEnd->format('d/m/Y')],
            'chart' => $chart,
            'insight' => $this->weekdayInsight($chart['no_show']),
            'top_patients' => $this->agenda
                ->topPatientsByNoShowBetween($userId, $startUtc, $endUtc, self::TOP_LIMIT)
                ->map(fn ($row) => $this->topPatient($row))
                ->all(),
        ];
    }

    /**
     * Horas agendadas contra horas disponíveis nas configurações da agenda (dias, horários, almoço, janta e feriados).
     * A taxa geral e a semanal são por horas; dia da semana e mapa de calor são por horários (slots) ocupados.
     */
    public function occupancy(int $userId, string $timezone, string $startDate, string $endDate): array
    {
        [$start, $end] = $this->period($timezone, $startDate, $endDate);
        [$previousStart, $previousEnd] = $this->previousPeriod($start, $end);

        $settings = $this->agendaSettingService->details($userId);
        $minutes = $this->agendaService->sessionMinutes($settings);

        $booked = $this->bookedBetween($userId, $start, $end, ['scheduled_on', 'ends_at', 'status'])
            ->map(fn ($row) => $this->bookedInterval($row, $timezone, $minutes));
        $previousBooked = $this->bookedBetween($userId, $previousStart, $previousEnd, ['scheduled_on', 'ends_at'])
            ->map(fn ($row) => $this->bookedInterval($row, $timezone, $minutes));

        $slots = $this->occupancySlots($booked, $start, $end, $settings, $minutes, Carbon::now($timezone));

        $capacityHours = round($slots['total'] * $minutes / 60, 1);
        $bookedHours = $this->sumHours($booked);
        $rate = $this->occupancyRate($bookedHours, $capacityHours);

        $previousCapacityHours = round($this->countSlots($previousStart, $previousEnd, $settings, $minutes) * $minutes / 60, 1);
        $previousBookedHours = $this->sumHours($previousBooked);
        $previousRate = $this->occupancyRate($previousBookedHours, $previousCapacityHours);

        $statuses = $booked->countBy(fn (array $row) => $row['status']?->value);

        return [
            'configured' => $settings !== null,
            'booked_hours' => $bookedHours,
            'capacity_hours' => $capacityHours,
            'free_hours' => max(0.0, round($capacityHours - $bookedHours, 1)),
            'occupancy_rate' => $rate,
            'variation' => $rate !== null && $previousRate !== null ? round($rate - $previousRate, 1) : null,
            'booked_variation' => $this->variation($bookedHours, $previousBookedHours),
            'sessions' => $booked->count(),
            'sessions_breakdown' => [
                'completed' => $statuses->get(AgendaStatusEnum::COMPLETED->value, 0),
                'upcoming' => $statuses->get(AgendaStatusEnum::SCHEDULED->value, 0) + $statuses->get(AgendaStatusEnum::CONFIRMED->value, 0),
                'no_show' => $statuses->get(AgendaStatusEnum::NO_SHOW->value, 0),
            ],
            'free_slots_upcoming' => $slots['free_upcoming'],
            'idle_slots_past' => $slots['idle_past'],
            'outside_hours' => $booked->count() - count($slots['matched']),
            'period' => ['start' => $start->format('d/m/Y'), 'end' => $end->format('d/m/Y')],
            'previous_period' => ['start' => $previousStart->format('d/m/Y'), 'end' => $previousEnd->format('d/m/Y')],
            'chart' => $this->weeklyOccupancy($booked, $slots['weeks'], $start, $end, $minutes),
            'by_weekday' => $this->weekdayOccupancy($slots['weekdays']),
            'heatmap' => $this->occupancyHeatmap($slots['grid']),
            'insight' => $this->occupancyInsight($slots['grid']),
        ];
    }

    public function attendanceExport(int $userId, string $timezone, string $startDate, string $endDate): AttendanceReportExport
    {
        [$start, $end] = $this->period($timezone, $startDate, $endDate);

        return new AttendanceReportExport($this->agenda, $userId, $start->utc(), $end->utc(), $timezone);
    }

    /**
     * @return array{completed: int, no_show: int, canceled: int, total: int, lost_revenue: float}
     */
    private function attendanceIndicators(int $userId, Carbon $startUtc, Carbon $endUtc): array
    {
        $counts = $this->agenda->countByStatusBetween($userId, $startUtc, $endUtc);
        $completed = $counts[AgendaStatusEnum::COMPLETED->value] ?? 0;
        $noShow = $counts[AgendaStatusEnum::NO_SHOW->value] ?? 0;
        $canceled = $counts[AgendaStatusEnum::CANCELED->value] ?? 0;

        return [
            'completed' => $completed,
            'no_show' => $noShow,
            'canceled' => $canceled,
            'total' => $completed + $noShow + $canceled,
            'lost_revenue' => $noShow > 0
                ? $this->agenda->sumPriceByStatusBetween($userId, AgendaStatusEnum::NO_SHOW, $startUtc, $endUtc)
                : 0.0,
        ];
    }

    /**
     * Taxa sobre as sessões finalizadas e a variação em pontos percentuais frente ao período anterior
     * (nula quando o período anterior não teve sessões finalizadas).
     *
     * @return array{rate: float, count: int, variation: ?float}
     */
    private function rateIndicator(array $current, array $previous, string $key): array
    {
        $rate = $this->percent($current[$key], $current['total']);

        return [
            'rate' => $rate,
            'count' => $current[$key],
            'variation' => $previous['total'] > 0 ? round($rate - $this->percent($previous[$key], $previous['total']), 1) : null,
        ];
    }

    /**
     * @return array{id: int, name: ?string, no_show: int, canceled: int, total: int, rate: float, attention: bool}
     */
    private function topPatient(object $row): array
    {
        $noShow = (int) $row->no_show;
        $rate = $this->percent($noShow, (int) $row->total);

        return [
            'id' => (int) $row->patient_id,
            'name' => $row->patient?->name,
            'no_show' => $noShow,
            'canceled' => (int) $row->canceled,
            'total' => (int) $row->total,
            'rate' => $rate,
            'attention' => $noShow >= self::ATTENTION_MIN_NO_SHOW && $rate >= self::ATTENTION_RATE,
        ];
    }

    /**
     * Dia(s) da semana que concentram as faltas. Só gera o texto com faltas suficientes e no máximo
     * dois dias empatados; acima disso as faltas estão espalhadas e o destaque não ajuda.
     *
     * @param  int[]  $noShow  faltas indexadas pelo dia da semana (0 = domingo)
     */
    private function weekdayInsight(array $noShow): ?string
    {
        $total = array_sum($noShow);

        if ($total < self::INSIGHT_MIN_NO_SHOW) {
            return null;
        }

        $max = max($noShow);
        $days = array_keys($noShow, $max, true);

        if (count($days) > 2) {
            return null;
        }

        $names = array_map(fn (int $day) => __('reports.weekdays')[$day], $days);

        return trans_choice('reports.attendance_insight', count($days), [
            'days' => implode(' '.__('reports.and').' ', $names),
            'percent' => (int) round($max * count($days) / $total * 100),
        ]);
    }

    private function percent(int $part, int $total): float
    {
        return $total > 0 ? round(($part / $total) * 100, 1) : 0.0;
    }

    /**
     * @return array{categories: string[], no_show: int[], canceled: int[]}
     */
    private function weekdayChart(Collection $closed, string $timezone): array
    {
        $noShow = array_fill(0, 7, 0);
        $canceled = array_fill(0, 7, 0);

        foreach ($closed as $row) {
            $weekday = $row->scheduled_on->copy()->setTimezone($timezone)->dayOfWeek;

            match ($row->status) {
                AgendaStatusEnum::NO_SHOW => $noShow[$weekday]++,
                AgendaStatusEnum::CANCELED => $canceled[$weekday]++,
                default => null,
            };
        }

        return ['categories' => __('reports.weekdays_short'), 'no_show' => $noShow, 'canceled' => $canceled];
    }

    private function bookedBetween(int $userId, Carbon $start, Carbon $end, array $cols): Collection
    {
        return $this->agenda->listByStatusesBetween(
            $userId,
            [AgendaStatusEnum::SCHEDULED, AgendaStatusEnum::CONFIRMED, AgendaStatusEnum::NO_SHOW, AgendaStatusEnum::COMPLETED],
            $start->copy()->utc(),
            $end->copy()->utc(),
            $cols,
        );
    }

    /**
     * Sessão no fuso do usuário; sem término gravado, assume a duração configurada.
     *
     * @return array{start: Carbon, end: Carbon, status: ?AgendaStatusEnum}
     */
    private function bookedInterval(Agenda $row, string $timezone, int $minutes): array
    {
        $start = $row->scheduled_on->copy()->setTimezone($timezone);
        $status = $row->status;

        return [
            'start' => $start,
            'end' => $row->ends_at ? $row->ends_at->copy()->setTimezone($timezone) : $start->copy()->addMinutes($minutes),
            'status' => $status instanceof AgendaStatusEnum ? $status : AgendaStatusEnum::tryFrom((string) $status),
        ];
    }

    private function sumHours(Collection $booked): float
    {
        return round($booked->sum(fn (array $row) => abs($row['start']->diffInMinutes($row['end']))) / 60, 1);
    }

    private function occupancyRate(float $bookedHours, float $capacityHours): ?float
    {
        return $capacityHours > 0 ? min(100.0, round(($bookedHours / $capacityHours) * 100, 1)) : null;
    }

    private function countSlots(Carbon $start, Carbon $end, ?AgendaSettings $settings, int $minutes): int
    {
        $total = 0;

        for ($day = $start->copy()->startOfDay(); $day->lte($end); $day->addDay()) {
            $total += count($this->agendaService->workingSlots($day, $settings, $minutes));
        }

        return $total;
    }

    /**
     * Percorre os horários de atendimento do período marcando os que têm sessão, e agrega por semana,
     * dia da semana e dia da semana × hora. Horários livres se dividem entre os que ainda podem ser
     * preenchidos (a partir de agora) e os ociosos (já passaram).
     */
    private function occupancySlots(Collection $booked, Carbon $start, Carbon $end, ?AgendaSettings $settings, int $minutes, Carbon $now): array
    {
        $byDay = $booked->groupBy(fn (array $row) => $row['start']->toDateString());
        $result = ['total' => 0, 'free_upcoming' => 0, 'idle_past' => 0, 'matched' => [], 'weeks' => [], 'weekdays' => [], 'grid' => []];

        for ($day = $start->copy()->startOfDay(); $day->lte($end); $day->addDay()) {
            $sessions = $byDay->get($day->toDateString(), collect());
            $week = $day->copy()->startOfWeek(Carbon::MONDAY)->toDateString();

            foreach ($this->agendaService->workingSlots($day, $settings, $minutes) as $time) {
                $slotStart = $day->copy()->setTimeFromTimeString($time);
                $slotEnd = $slotStart->copy()->addMinutes($minutes);
                $busy = false;

                foreach ($sessions as $index => $session) {
                    if ($session['start']->lt($slotEnd) && $session['end']->gt($slotStart)) {
                        $busy = true;
                        $result['matched'][$day->toDateString().'-'.$index] = true;
                    }
                }

                $result['total']++;
                $result['weeks'][$week] = ($result['weeks'][$week] ?? 0) + 1;
                $this->addSlot($result['weekdays'][$day->dayOfWeek], $busy);
                $this->addSlot($result['grid'][$day->dayOfWeek][$slotStart->hour], $busy);

                if (! $busy) {
                    $slotStart->gte($now) ? $result['free_upcoming']++ : $result['idle_past']++;
                }
            }
        }

        return $result;
    }

    private function addSlot(?array &$cell, bool $busy): void
    {
        $cell ??= ['total' => 0, 'busy' => 0];
        $cell['total']++;
        $cell['busy'] += (int) $busy;
    }

    /**
     * Horas agendadas e capacidade por semana (segunda a domingo), com rótulos limitados ao período.
     *
     * @return array{categories: string[], booked_hours: float[], capacity_hours: float[], rate: array<?float>}
     */
    private function weeklyOccupancy(Collection $booked, array $weekSlots, Carbon $start, Carbon $end, int $minutes): array
    {
        $bookedMinutes = $booked
            ->groupBy(fn (array $row) => $row['start']->copy()->startOfWeek(Carbon::MONDAY)->toDateString())
            ->map(fn (Collection $rows) => $rows->sum(fn (array $row) => abs($row['start']->diffInMinutes($row['end']))));

        $chart = ['categories' => [], 'booked_hours' => [], 'capacity_hours' => [], 'rate' => []];

        for ($week = $start->copy()->startOfWeek(Carbon::MONDAY); $week->lte($end); $week->addWeek()) {
            $from = $week->max($start->copy()->startOfDay());
            $to = $week->copy()->endOfWeek(Carbon::SUNDAY)->min($end);
            $key = $week->toDateString();

            $bookedHours = round($bookedMinutes->get($key, 0) / 60, 1);
            $capacityHours = round(($weekSlots[$key] ?? 0) * $minutes / 60, 1);

            $chart['categories'][] = $from->isSameDay($to) ? $from->format('d/m') : $from->format('d/m').' – '.$to->format('d/m');
            $chart['booked_hours'][] = $bookedHours;
            $chart['capacity_hours'][] = $capacityHours;
            $chart['rate'][] = $this->occupancyRate($bookedHours, $capacityHours);
        }

        return $chart;
    }

    /**
     * Dias da semana com expediente, de segunda a domingo.
     *
     * @return array<array{label: string, busy: int, total: int, rate: float}>
     */
    private function weekdayOccupancy(array $weekdays): array
    {
        $rows = [];

        foreach (self::WEEK_ORDER as $weekday) {
            if (isset($weekdays[$weekday])) {
                $rows[] = ['label' => __('reports.weekdays')[$weekday]] + $weekdays[$weekday] + ['rate' => $this->percent($weekdays[$weekday]['busy'], $weekdays[$weekday]['total'])];
            }
        }

        return $rows;
    }

    /**
     * Grade dia da semana × hora cheia; células sem expediente ficam nulas.
     *
     * @return array{hours: int[], rows: array<array{label: string, cells: array<?array{busy: int, total: int, rate: float}>}>}
     */
    private function occupancyHeatmap(array $grid): array
    {
        $hours = collect($grid)->flatMap(fn (array $row) => array_keys($row))->unique()->sort()->values()->all();
        $rows = [];

        foreach (self::WEEK_ORDER as $weekday) {
            if (! isset($grid[$weekday])) {
                continue;
            }

            $rows[] = [
                'label' => __('reports.weekdays_short')[$weekday],
                'cells' => array_map(
                    fn (int $hour) => isset($grid[$weekday][$hour])
                        ? $grid[$weekday][$hour] + ['rate' => $this->percent($grid[$weekday][$hour]['busy'], $grid[$weekday][$hour]['total'])]
                        : null,
                    $hours,
                ),
            ];
        }

        return ['hours' => $hours, 'rows' => $rows];
    }

    /**
     * Turno (dia da semana + manhã/tarde/noite) com a menor ocupação. Só aparece quando já há sessões
     * no período e o turno tem horários suficientes; sem isso a comparação não diz nada.
     */
    private function occupancyInsight(array $grid): ?string
    {
        $shifts = [];

        foreach ($grid as $weekday => $hours) {
            foreach ($hours as $hour => $cell) {
                $shift = $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'night');
                $this->addSlots($shifts[$weekday.'|'.$shift], $cell);
            }
        }

        $busy = array_sum(array_column($shifts, 'busy'));
        $total = array_sum(array_column($shifts, 'total'));
        $candidates = array_filter($shifts, fn (array $cell) => $cell['total'] >= self::INSIGHT_MIN_SLOTS);

        if ($busy === 0 || count($candidates) < 2) {
            return null;
        }

        uasort($candidates, fn (array $a, array $b) => $a['busy'] / $a['total'] <=> $b['busy'] / $b['total']);
        $key = array_key_first($candidates);
        $rate = $this->percent($candidates[$key]['busy'], $candidates[$key]['total']);

        if ($rate >= $this->percent($busy, $total)) {
            return null;
        }

        [$weekday, $shift] = explode('|', $key);

        return __('reports.occupancy_insight', [
            'day' => __('reports.weekdays')[(int) $weekday],
            'shift' => __('reports.shifts')[$shift],
            'percent' => (int) round($rate),
        ]);
    }

    private function addSlots(?array &$cell, array $slots): void
    {
        $cell ??= ['total' => 0, 'busy' => 0];
        $cell['total'] += $slots['total'];
        $cell['busy'] += $slots['busy'];
    }
}
