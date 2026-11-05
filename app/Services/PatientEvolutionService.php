<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AgendaStatusEnum;
use App\Models\Agenda;
use App\Models\Patients;
use Illuminate\Support\Collection;

/**
 * Indicadores de frequência do paciente para o painel de evolução. Usa só a agenda (dados que o
 * servidor já conhece); as escalas são cifradas e entram no gráfico pelo navegador.
 */
readonly class PatientEvolutionService
{
    public const PERIODS = [3, 6, 12, 0];

    public function __construct(
        private Agenda $agenda,
        private Patients $patients,
    ) {
    }

    /**
     * @param  int  $months  janela em meses; 0 = todo o acompanhamento
     */
    public function attendance(int $patientId, int $userId, string $timezone, int $months): array
    {
        if (! $this->patients->findById(id: $patientId, userId: $userId, cols: ['id'])) {
            throw new \DomainException(__('scales.patient_not_found'));
        }

        $now = now($timezone);
        $since = $months > 0 ? $now->copy()->subMonths($months)->startOfDay()->utc() : null;
        $sessions = $this->agenda->listClosedByPatient(userId: $userId, patientId: $patientId, sinceUtc: $since, untilUtc: $now->copy()->utc());

        $completed = $sessions->where('status', AgendaStatusEnum::COMPLETED)->values();
        $noShow = $sessions->where('status', AgendaStatusEnum::NO_SHOW)->count();
        $first = $this->agenda->firstCompletedByPatient(userId: $userId, patientId: $patientId);

        return [
            'months' => $months,
            'completed' => $completed->count(),
            'no_show' => $noShow,
            'canceled' => $sessions->where('status', AgendaStatusEnum::CANCELED)->count(),
            'presence_rate' => $completed->count() + $noShow > 0 ? round($completed->count() / ($completed->count() + $noShow) * 100, 1) : null,
            'avg_interval_days' => $this->averageIntervalDays($completed),
            'first_session_on' => $first?->scheduled_on?->copy()->setTimezone($timezone),
            'last_session_on' => $completed->last()?->scheduled_on?->copy()->setTimezone($timezone),
        ];
    }

    /**
     * Média de dias entre sessões realizadas consecutivas (null com menos de duas).
     */
    private function averageIntervalDays(Collection $completed): ?float
    {
        if ($completed->count() < 2) {
            return null;
        }

        $intervals = $completed->sliding(2)->map(fn (Collection $pair) => $pair->first()->scheduled_on->diffInDays($pair->last()->scheduled_on));

        return round($intervals->avg(), 1);
    }
}
