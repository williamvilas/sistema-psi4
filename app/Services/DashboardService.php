<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AgendaStatusEnum;
use App\Enums\PaymentOrderStatusEnum;
use App\Helpers\DashboardCache;
use App\Models\Agenda;
use App\Models\Patients;
use App\Models\PatientScaleApplications;
use App\Models\PaymentOrder;
use Carbon\Carbon;
use Illuminate\Support\Collection;

readonly class DashboardService
{
    /** Dias sem sessão para considerar o paciente em risco de abandono. */
    private const INACTIVITY_DAYS = 30;

    /** Depois deste limite o paciente deixa de ser alertado (provável alta/encerramento). */
    private const INACTIVITY_MAX_DAYS = 90;

    /** Faltas seguidas (sessões mais recentes) para alertar. */
    private const NO_SHOW_STREAK = 2;

    private const NO_SHOW_LOOKBACK_DAYS = 60;

    /** Dias desde a última escala aplicada para sugerir a reaplicação. */
    private const SCALE_REAPPLY_DAYS = 30;

    /** Itens exibidos por bloco; o total completo vai no contador. */
    private const LIST_LIMIT = 5;

    private const ATTENDANCE_DAYS = 30;

    private const CHART_WEEKS = 8;

    public function __construct(
        private Agenda $agenda,
        private Patients $patients,
        private PaymentOrder $paymentOrder,
        private PatientScaleApplications $scaleApplications,
        private AgendaReportService $agendaReportService,
    ) {
    }

    /**
     * Dados do dashboard em cache de 5 minutos por usuário. O cache é removido a cada alteração
     * de agenda, pacientes, pagamentos ou prontuários (ver DashboardCache::forget).
     */
    public function report(int $userId, string $timezone): array
    {
        $report = DashboardCache::get($userId);

        if ($report !== null) {
            return $report;
        }

        $report = $this->build($userId, $timezone);

        DashboardCache::put($userId, $report);

        return $report;
    }

    private function build(int $userId, string $timezone): array
    {
        $now = now($timezone);
        $todayEnd = $now->copy()->endOfDay();

        return [
            'pending_attendance' => $this->agenda->countPendingAttendance($userId, $now->copy()->utc()),
            'pending_payments' => $this->paymentOrder->countPending($userId, $now->toDateString()),
            'scheduled_today' => $this->agenda->countByStatusBetween(
                $userId,
                $now->copy()->startOfDay()->utc(),
                $todayEnd->copy()->utc(),
            )[AgendaStatusEnum::SCHEDULED->value] ?? 0,
            'birthdays_today' => $this->patients->countByBirthdayDay($userId, $now->format('m-d')),
            'today_sessions' => $this->todaySessions($userId, $timezone),
            'overdue_payments' => $this->overduePayments($userId, $now->toDateString()),
            'alerts' => $this->alerts($userId, $timezone),
            'kpis' => $this->kpis($userId, $timezone),
            'weekly_sessions' => $this->weeklySessions($userId, $timezone),
        ];
    }

    /**
     * Indicadores de saúde do consultório, cada um com o comparativo do período anterior.
     */
    private function kpis(int $userId, string $timezone): array
    {
        $now = now($timezone);
        $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY);
        $occupancy = $this->agendaReportService->occupancy(
            $userId,
            $timezone,
            $weekStart->toDateString(),
            $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
        );

        return [
            'revenue' => $this->revenue($userId, $now),
            'attendance' => $this->attendance($userId, $now),
            'occupancy' => ['rate' => $occupancy['occupancy_rate'], 'free_hours' => $occupancy['free_hours']],
        ];
    }

    /**
     * Recebido no mês até hoje contra o mesmo intervalo do mês anterior.
     */
    private function revenue(int $userId, Carbon $now): array
    {
        $previousEnd = $now->copy()->subMonthNoOverflow();
        $current = $this->paymentOrder->summarizeByStatusAndPeriod(
            $userId,
            PaymentOrderStatusEnum::PAID,
            $now->copy()->startOfMonth()->utc(),
            $now->copy()->utc(),
        )['total'];
        $previous = $this->paymentOrder->summarizeByStatusAndPeriod(
            $userId,
            PaymentOrderStatusEnum::PAID,
            $previousEnd->copy()->startOfMonth()->utc(),
            $previousEnd->utc(),
        )['total'];

        return [
            'total' => round($current, 2),
            'variation' => $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : null,
        ];
    }

    /**
     * Comparecimento (finalizadas sobre finalizadas + faltas) nos últimos 30 dias e variação em pontos percentuais.
     */
    private function attendance(int $userId, Carbon $now): array
    {
        $current = $this->attendanceRate($userId, $now->copy()->subDays(self::ATTENDANCE_DAYS), $now);
        $previous = $this->attendanceRate(
            $userId,
            $now->copy()->subDays(self::ATTENDANCE_DAYS * 2),
            $now->copy()->subDays(self::ATTENDANCE_DAYS),
        );

        return [
            'rate' => $current,
            'variation' => $current !== null && $previous !== null ? round($current - $previous, 1) : null,
        ];
    }

    private function attendanceRate(int $userId, Carbon $start, Carbon $end): ?float
    {
        $counts = $this->agenda->countByStatusBetween($userId, $start->copy()->utc(), $end->copy()->utc());
        $completed = $counts[AgendaStatusEnum::COMPLETED->value] ?? 0;
        $base = $completed + ($counts[AgendaStatusEnum::NO_SHOW->value] ?? 0);

        return $base > 0 ? round(($completed / $base) * 100, 1) : null;
    }

    /**
     * Sessões encerradas por semana (finalizadas, faltas e cancelamentos) nas últimas semanas.
     *
     * @return array{categories: string[], completed: int[], no_show: int[], canceled: int[]}
     */
    private function weeklySessions(int $userId, string $timezone): array
    {
        $now = now($timezone);
        $firstWeek = $now->copy()->startOfWeek(Carbon::MONDAY)->subWeeks(self::CHART_WEEKS - 1);

        $sessions = $this->agenda->listByStatusesBetween(
            $userId,
            [AgendaStatusEnum::COMPLETED, AgendaStatusEnum::NO_SHOW, AgendaStatusEnum::CANCELED],
            $firstWeek->copy()->utc(),
            $now->copy()->endOfDay()->utc(),
            ['scheduled_on', 'status'],
        )->groupBy(fn (Agenda $session) => $session->scheduled_on->copy()->setTimezone($timezone)->startOfWeek(Carbon::MONDAY)->format('Y-m-d'));

        $chart = ['categories' => [], 'completed' => [], 'no_show' => [], 'canceled' => []];

        for ($week = $firstWeek->copy(); $week->lte($now); $week->addWeek()) {
            $weekSessions = $sessions->get($week->format('Y-m-d'), collect());

            $chart['categories'][] = $week->format('d/m');
            $chart['completed'][] = $weekSessions->where('status', AgendaStatusEnum::COMPLETED)->count();
            $chart['no_show'][] = $weekSessions->where('status', AgendaStatusEnum::NO_SHOW)->count();
            $chart['canceled'][] = $weekSessions->where('status', AgendaStatusEnum::CANCELED)->count();
        }

        return $chart;
    }

    private function todaySessions(int $userId, string $timezone): array
    {
        $now = now($timezone);

        return $this->agenda->listNotCanceledBetweenWithPatient(
            $userId,
            $now->copy()->startOfDay()->utc(),
            $now->copy()->endOfDay()->utc(),
            ['id', 'patient_id', 'scheduled_on', 'ends_at', 'status', 'room_link'],
        )
            ->filter(fn (Agenda $session) => $session->patient !== null)
            ->map(fn (Agenda $session) => [
                'patient_id' => $session->patient_id,
                'name' => $session->patient->name,
                'time' => $session->scheduled_on->copy()->setTimezone($timezone)->format('H:i'),
                'status' => $session->status->value,
                'room_link' => $session->room_link,
            ])
            ->values()
            ->all();
    }

    private function overduePayments(int $userId, string $today): array
    {
        $summary = $this->paymentOrder->summarizeOverdue($userId, $today);

        $items = $this->paymentOrder->listOverdue($userId, $today, self::LIST_LIMIT)
            ->filter(fn (PaymentOrder $order) => $order->patient !== null)
            ->map(fn (PaymentOrder $order) => [
                'id' => $order->id,
                'name' => $order->patient->name,
                'phone' => $order->patient->phone,
                'amount' => (float) $order->amount,
                'days_overdue' => (int) $order->due_date->diffInDays($today),
            ])
            ->values()
            ->all();

        return $summary + ['items' => $items];
    }

    /**
     * Alertas sobre pacientes que precisam de atenção. Cada alerta traz o total e os primeiros itens.
     */
    private function alerts(int $userId, string $timezone): array
    {
        $now = now($timezone);

        $inactive = $this->agenda->listInactivePatients(
            $userId,
            $now->copy()->subDays(self::INACTIVITY_MAX_DAYS)->utc(),
            $now->copy()->subDays(self::INACTIVITY_DAYS)->utc(),
            $now->copy()->utc(),
        );
        $closed = $this->agenda->listClosedSince($userId, $now->copy()->subDays(self::NO_SHOW_LOOKBACK_DAYS)->utc());
        $noShowStreaks = $this->noShowStreaks($closed);
        $scaleOverdue = $this->scaleOverdue($userId, $closed, $now);

        $patients = $this->patients->findByIds(
            array_values(array_unique([...$inactive->pluck('patient_id')->all(), ...array_keys($noShowStreaks), ...array_keys($scaleOverdue)])),
            $userId,
            ['id', 'name', 'phone'],
        );

        return [
            'abandonment' => $this->buildAlert($patients, $inactive->pluck('last_session_on', 'patient_id')->all(),
                fn ($lastSessionOn) => 'Última sessão há '.(int) Carbon::parse($lastSessionOn, 'UTC')->diffInDays($now->copy()->utc()).' dias'),
            'no_show_streak' => $this->buildAlert($patients, $noShowStreaks, fn (int $streak) => $streak.' faltas seguidas'),
            'scale_overdue' => $this->buildAlert($patients, $scaleOverdue, fn (int $days) => 'Última escala há '.$days.' dias'),
        ];
    }

    /**
     * @param  array<int, mixed>  $detailsByPatient  detalhe bruto por paciente (ordem preservada)
     */
    private function buildAlert(Collection $patients, array $detailsByPatient, callable $detail): array
    {
        $items = collect($detailsByPatient)
            ->filter(fn ($value, $patientId) => $patients->has($patientId))
            ->map(fn ($value, $patientId) => [
                'patient_id' => $patientId,
                'name' => $patients[$patientId]->name,
                'phone' => $patients[$patientId]->phone,
                'detail' => $detail($value),
            ])
            ->values();

        return ['count' => $items->count(), 'items' => $items->take(self::LIST_LIMIT)->all()];
    }

    /**
     * @return array<int, int> total de faltas seguidas (nas sessões mais recentes) por paciente
     */
    private function noShowStreaks(Collection $closed): array
    {
        $streaks = [];

        foreach ($closed->groupBy('patient_id') as $patientId => $sessions) {
            $streak = $sessions->takeWhile(fn (Agenda $session) => $session->status === AgendaStatusEnum::NO_SHOW)->count();

            if ($streak >= self::NO_SHOW_STREAK) {
                $streaks[$patientId] = $streak;
            }
        }

        return $streaks;
    }

    /**
     * Pacientes em atendimento (sessão realizada nos últimos dias) cuja última escala é antiga. Usa só a data:
     * respostas e pontuação são cifradas e o servidor não as conhece.
     *
     * @return array<int, int> dias desde a última escala por paciente, do mais antigo para o mais recente
     */
    private function scaleOverdue(int $userId, Collection $closed, Carbon $now): array
    {
        $limitUtc = $now->copy()->subDays(self::SCALE_REAPPLY_DAYS)->utc();
        $inTreatment = $closed
            ->filter(fn (Agenda $session) => $session->status === AgendaStatusEnum::COMPLETED && $session->scheduled_on->gte($limitUtc))
            ->pluck('patient_id')
            ->unique()
            ->flip();

        $today = $now->copy()->startOfDay();

        return $this->scaleApplications->lastAppliedOnByPatient($userId)
            ->filter(fn (string $lastAppliedOn, int $patientId) => $inTreatment->has($patientId))
            ->map(fn (string $lastAppliedOn) => (int) Carbon::parse($lastAppliedOn, $now->getTimezone())->diffInDays($today))
            ->filter(fn (int $days) => $days >= self::SCALE_REAPPLY_DAYS)
            ->sortDesc()
            ->all();
    }
}
