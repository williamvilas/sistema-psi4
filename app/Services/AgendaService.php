<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AgendaStatusEnum;
use App\Enums\AgendaTypeEnum;
use App\Enums\AttendanceModeEnum;
use App\Enums\FeatureUsageEnum;
use App\Enums\PaginateCacheContextEnum;
use App\Helpers\PaginateCache;
use App\Models\Agenda;
use App\Models\AgendaSettings;
use App\Models\Patients;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

readonly class AgendaService
{
    // Carbon::dayOfWeek (0 = domingo) => chave usada em agenda_settings.agenda_days
    private const DAY_KEYS = [0 => 'dom', 1 => 'seg', 2 => 'ter', 3 => 'qua', 4 => 'qui', 5 => 'sex', 6 => 'sab'];

    public function __construct(
        private Agenda $agenda,
        private AgendaSettingService $agendaSettingService,
        private GoogleCalendarService $googleCalendarService,
        private Patients $patients = new Patients,
        private AgendaVideoRoomService $agendaVideoRoomService = new AgendaVideoRoomService,
        private PlanLimitService $planLimitService = new PlanLimitService,
    ) {

    }

    /**
     * Convite para conectar o Google Agenda: só para quem nunca usou a agenda,
     * nunca conectou uma conta Google e não dispensou o convite.
     */
    public function shouldShowGoogleCalendarHero(User $user): bool
    {
        if ($user->hasDismissedGoogleCalendarHero()) {
            return false;
        }

        if ($this->googleCalendarService->details($user->id) !== null) {
            return false;
        }

        return ! $this->agenda->existsByUser($user->id);
    }

    public function dismissGoogleCalendarHero(User $user): void
    {
        $user->dismissGoogleCalendarHero();
    }

    /**
     * Horários livres do dia conforme as configurações da agenda do psicólogo, com os tipos de consulta aceitos em cada um.
     *
     * @return array<string, string[]> horário (H:i) => tipos de consulta (agenda.type)
     */
    public function availableSlotTypes(int $userId, string $timezone, string $date): array
    {
        $settings = $this->agendaSettingService->details($userId);
        $day = Carbon::createFromFormat('Y-m-d', $date, $timezone)->startOfDay();
        $busy = $this->busyKeys($userId, $timezone, $day->copy(), $day->copy()->endOfDay());

        return $this->freeSlotTypes($day, $settings, $this->sessionMinutes($settings), $busy, now($timezone));
    }

    /**
     * Tipos de consulta presentes nos horários, na ordem do enum. Sem horário, só online.
     *
     * @param  array<string, string[]>  $slotTypes
     * @return string[]
     */
    public function typesOf(array $slotTypes): array
    {
        $present = array_merge([], ...array_values($slotTypes));
        $types = array_values(array_filter(
            array_map(fn (AgendaTypeEnum $type) => $type->value, AgendaTypeEnum::cases()),
            fn (string $type) => in_array($type, $present, true)
        ));

        return $types ?: [AgendaTypeEnum::ONLINE->value];
    }

    /**
     * Agendamento feito pelo psicólogo: respeita dias, horários, almoço, janta, feriados e duração das configurações.
     */
    public function storeByPsychologist(array $data, int $userId, string $timezone): bool
    {
        if (empty($data['patient_id']) || ! $this->patients->findById((int) $data['patient_id'], $userId, ['id'])) {
            throw new \DomainException(__('agenda.patient_not_found'));
        }

        $available = $this->availableSlotTypes($userId, $timezone, $data['date']);

        foreach ($data['timer'] as $time) {
            if (! isset($available[$time])) {
                throw new \DomainException(__('agenda.not_available'));
            }

            // Horário livre, mas de um período com outra modalidade (ex.: presencial num período online)
            if (! in_array($data['type'], $available[$time], true)) {
                throw new \DomainException(__('agenda.mode_not_allowed'));
            }
        }

        $data['duration'] = $this->sessionMinutes($this->agendaSettingService->details($userId));

        // Cada horário conta um agendamento no limite do plano
        return DB::transaction(function () use ($data, $userId, $timezone) {
            $this->planLimitService->consume(userId: $userId, feature: FeatureUsageEnum::AGENDA, amount: count($data['timer']));

            return $this->store($data, $userId, $timezone);
        });
    }

    public function sessionMinutes(?AgendaSettings $agenda): int
    {
        $default = (int) config('booking.default_session_minutes');

        $minutes = $this->timeToMinutes($agenda?->consultation_time);

        return $minutes !== null && $minutes > 0 ? $minutes : $default;
    }

    /**
     * Intervalo (min) entre o fim de uma consulta e o início da próxima. Opcional: sem valor, não há intervalo.
     */
    public function gapMinutes(?AgendaSettings $agenda): int
    {
        return $this->timeToMinutes($agenda?->available_time_minutes) ?? 0;
    }

    private function timeToMinutes(mixed $time): ?int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})/', (string) $time, $m)) {
            return null;
        }

        return ((int) $m[1] * 60) + (int) $m[2];
    }

    /**
     * Horários (Y-m-d H:i) já ocupados: consultas da PSI4 (com o intervalo entre consultas
     * antes e depois) e, quando o psicólogo ativou, compromissos do Google Agenda.
     */
    public function busyKeys(int $userId, string $timezone, Carbon $start, Carbon $end, bool $fresh = false): array
    {
        $settings = $this->agendaSettingService->details($userId);
        $gap = $this->gapMinutes($settings);
        $margin = $this->sessionMinutes($settings) + $gap;

        // Margem na busca para pegar consultas que começam fora da janela mas colidem com ela
        $appointments = $this->agenda
            ->busyPeriods($userId, $start->copy()->subMinutes($margin)->utc(), $end->copy()->addMinutes($margin)->utc())
            ->map(fn (array $p) => [
                'start' => Carbon::parse($p['start'])->setTimezone($timezone),
                'end' => Carbon::parse($p['end'])->setTimezone($timezone),
            ])
            ->all();

        $keys = [];

        foreach ($appointments as $appointment) {
            $keys[$appointment['start']->format('Y-m-d H:i')] = 0;
        }

        return $keys
            + $this->overlappingSlotKeys($settings, $start, $end, $appointments, $gap)
            + $this->googleBusyKeys($userId, $timezone, $start, $end, $fresh);
    }

    /**
     * Horários de atendimento que se sobrepõem a algum compromisso ocupado no Google.
     *
     * @return array<string, int>
     */
    private function googleBusyKeys(int $userId, string $timezone, Carbon $start, Carbon $end, bool $fresh = false): array
    {
        $intervals = $this->googleCalendarService->busyIntervals($userId, $start->copy()->utc(), $end->copy()->utc(), $fresh);

        if ($intervals === []) {
            return [];
        }

        // O intervalo entre consultas não se aplica aos eventos do Google
        return $this->overlappingSlotKeys($this->agendaSettingService->details($userId), $start, $end, $intervals, 0);
    }

    /**
     * Slots de atendimento que colidem com algum período, respeitando $gap minutos de folga antes e depois dele.
     *
     * @param  array<int, array{start: Carbon, end: Carbon}>  $periods
     * @return array<string, int>
     */
    private function overlappingSlotKeys(?AgendaSettings $settings, Carbon $start, Carbon $end, array $periods, int $gap): array
    {
        if ($periods === []) {
            return [];
        }

        $minutes = $this->sessionMinutes($settings);
        $keys = [];

        for ($day = $start->copy()->startOfDay(); $day->lte($end); $day->addDay()) {
            foreach ($this->workingSlots($day->copy(), $settings, $minutes) as $time) {
                $slotStart = $day->copy()->setTimeFromTimeString($time);
                $slotEnd = $slotStart->copy()->addMinutes($minutes);

                foreach ($periods as $period) {
                    if ($slotStart->lt($period['end']->copy()->addMinutes($gap)) && $slotEnd->copy()->addMinutes($gap)->gt($period['start'])) {
                        $keys[$slotStart->format('Y-m-d H:i')] = 0;

                        break;
                    }
                }
            }
        }

        return $keys;
    }

    /**
     * @param  ?string  $type  tipo de consulta (agenda.type); sem ele, horários de qualquer modalidade
     * @return string[] horários (H:i) livres do dia
     */
    public function freeSlots(Carbon $day, ?AgendaSettings $settings, int $minutes, array $busy, Carbon $minStart, ?string $type = null): array
    {
        $slots = $this->freeSlotTypes($day, $settings, $minutes, $busy, $minStart);

        if ($type !== null) {
            $slots = array_filter($slots, fn (array $types) => in_array($type, $types, true));
        }

        return array_keys($slots);
    }

    /**
     * @return array<string, string[]> horário (H:i) livre do dia => tipos de consulta aceitos nele
     */
    public function freeSlotTypes(Carbon $day, ?AgendaSettings $settings, int $minutes, array $busy, Carbon $minStart): array
    {
        return array_filter(
            $this->workingSlotTypes($day, $settings, $minutes),
            function (string $time) use ($day, $busy, $minStart) {
                $dateTime = $day->copy()->setTimeFromTimeString($time);

                return $dateTime->gte($minStart) && ! isset($busy[$dateTime->format('Y-m-d H:i')]);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Agrupa os horários livres por tipo de consulta, na ordem do dia.
     *
     * @param  array<string, string[]>  $slotTypes  retorno de freeSlotTypes
     * @return array<string, string[]> tipo de consulta => horários (H:i)
     */
    public function slotsByType(array $slotTypes): array
    {
        $byType = [];

        foreach ($slotTypes as $time => $types) {
            foreach ($types as $type) {
                $byType[$type][] = (string) $time;
            }
        }

        return $byType;
    }

    /**
     * @return string[] horários (H:i) de atendimento do dia, sem considerar a agenda já ocupada
     */
    public function workingSlots(Carbon $day, ?AgendaSettings $settings, int $minutes): array
    {
        return array_map('strval', array_keys($this->workingSlotTypes($day, $settings, $minutes)));
    }

    /**
     * Horários de atendimento do dia, gerados período a período (cada um com sua modalidade).
     *
     * @return array<string, string[]> horário (H:i) => tipos de consulta aceitos, sem considerar a agenda já ocupada
     */
    public function workingSlotTypes(Carbon $day, ?AgendaSettings $settings, int $minutes): array
    {
        if (! $settings) {
            return [];
        }

        $key = self::DAY_KEYS[$day->dayOfWeek];
        $enabled = ! empty($settings->agenda_days[$key]['enabled']);
        $periods = $settings->periodsForDay($key);

        $holiday = $settings->holidays->firstWhere('date', $day->toDateString());

        if ($holiday) {
            if (! $holiday->work_enabled) {
                return [];
            }

            $enabled = true;
            $periods = $this->holidayPeriods($periods, $holiday->start, $holiday->end, $settings->attendance_mode ?? AttendanceModeEnum::ONLINE);
        }

        if (! $enabled || $periods === []) {
            return [];
        }

        $blocked = [];

        foreach (['lunch', 'dinner'] as $meal) {
            $mealStart = $settings->{$meal.'_start'};
            $mealEnd = $settings->{$meal.'_end'};

            if ($settings->{$meal.'_enabled'} && $mealStart && $mealEnd) {
                $blocked[] = [
                    $day->copy()->setTimeFromTimeString($mealStart),
                    $day->copy()->setTimeFromTimeString($mealEnd),
                ];
            }
        }

        $step = $minutes + $this->gapMinutes($settings);
        $slots = [];

        foreach ($periods as $period) {
            $types = $period['mode']->types();
            $limit = $day->copy()->setTimeFromTimeString($period['end']);

            // O próximo horário é início + duração + intervalo; ao colidir com almoço/janta, retoma no fim do bloco
            $t = $day->copy()->setTimeFromTimeString($period['start']);

            while ($t->copy()->addMinutes($minutes)->lte($limit)) {
                $slotEnd = $t->copy()->addMinutes($minutes);

                foreach ($blocked as [$blockStart, $blockEnd]) {
                    if ($t->lt($blockEnd) && $slotEnd->gt($blockStart)) {
                        $t = $blockEnd->copy();

                        continue 2;
                    }
                }

                $time = $t->format('H:i');
                $slots[$time] = isset($slots[$time]) ? array_values(array_unique([...$slots[$time], ...$types])) : $types;
                $t->addMinutes($step);
            }
        }

        if (count($periods) > 1) {
            ksort($slots, SORT_STRING);
        }

        return $slots;
    }

    /**
     * Feriado com horário próprio: com um período, o horário do feriado substitui o do dia (mantendo a modalidade);
     * com vários, cada período fica limitado à faixa do feriado. Dia sem horário usa a faixa do feriado na modalidade geral.
     *
     * @param  array<int, array{start: string, end: string, mode: AttendanceModeEnum}>  $periods
     * @return array<int, array{start: string, end: string, mode: AttendanceModeEnum}>
     */
    private function holidayPeriods(array $periods, ?string $start, ?string $end, AttendanceModeEnum $fallback): array
    {
        $start = $start ? substr($start, 0, 5) : null;
        $end = $end ? substr($end, 0, 5) : null;

        if (! $start && ! $end) {
            return $periods;
        }

        if (count($periods) <= 1) {
            $period = $periods[0] ?? ['start' => null, 'end' => null, 'mode' => $fallback];
            $start ??= $period['start'];
            $end ??= $period['end'];

            return $start && $end ? [['start' => $start, 'end' => $end, 'mode' => $period['mode']]] : [];
        }

        $clipped = [];

        foreach ($periods as $period) {
            $from = max(substr($period['start'], 0, 5), $start ?? '00:00');
            $to = min(substr($period['end'], 0, 5), $end ?? '23:59');

            if ($from < $to) {
                $clipped[] = ['start' => $from, 'end' => $to, 'mode' => $period['mode']];
            }
        }

        return $clipped;
    }

    public function store(array $data, int $userId, string $timezone): bool
    {
        $online = $data['type'] === AgendaTypeEnum::ONLINE->value;

        try {
            DB::transaction(function () use ($data, $userId, $timezone, $online) {

                $date = $data['date'];
                $timers = $data['timer'];

                $dateTimes = collect($timers)->map(function ($time) use ($date, $timezone) {
                    return Carbon::createFromFormat(
                        format: 'Y-m-d H:i',
                        time: "{$date} {$time}",
                        timezone: $timezone
                    );
                });

                $manualLink = $data['room_link'] ?? null;

                foreach ($dateTimes as $dateTime) {

                    $attributes = [
                        'patient_id' => $data['patient_id'],
                        'service_id' => $data['service_id'] ?? null,
                        'type' => $data['type'],
                        'scheduled_on' => $dateTime->copy()->setTimezone('UTC'),
                        'ends_at' => $dateTime->copy()->setTimezone('UTC')->addMinutes($data['duration'] ?? 60),
                        'status' => AgendaStatusEnum::SCHEDULED->value,
                        'price' => $data['price'] ?? 0,
                        'notes' => $data['notes'] ?? null,
                        // Consulta presencial não tem sala de vídeo; a online sem link próprio ganha a sala do PSI4 logo abaixo
                        'room_link' => $online ? ($manualLink ?: null) : null,
                    ];

                    $scheduledOn = $attributes['scheduled_on'];

                    $agenda = $this->agenda->recycleCanceled($userId, $scheduledOn, $attributes)
                        ?? $this->agenda->create($attributes + ['user_id' => $userId]);

                    // Antes do commit: a sincronização com o Google (afterCommit) já leva o link como local do evento
                    if ($online && ! $manualLink) {
                        $this->agendaVideoRoomService->attach($agenda->id);
                    }

                }

            });

            return true;

        } catch (QueryException $e) {

            $message = $e->getMessage();

            if (str_contains($message, 'Duplicate')) {
                throw new \DomainException(__('agenda.not_available'));
            }

            throw $e;
        }
    }

    public function cancel(int $id, int $userId): void
    {
        if (! $this->agenda->cancel($userId, $id)) {
            throw new \DomainException(__('agenda.not_found'));
        }
    }

    public function confirmAttendance(int $id, int $userId, AgendaStatusEnum $status): void
    {
        if (! $this->agenda->confirmAttendance($userId, $id, $status)) {
            throw new \DomainException(__('agenda.attendance_not_found'));
        }
    }

    public function paginate(array $params, int $userId, int $limit, ?int $patientId = null, ?Carbon $start = null, ?Carbon $end = null, bool $pendingAttendance = false): LengthAwarePaginator
    {
        $status = $params['status'] ?? null;
        $type = $params['type'] ?? null;

        return PaginateCache::remember(
            context: PaginateCacheContextEnum::AGENDA,
            userId: $userId,
            params: [
                'scope' => 'filters',
                'patient_id' => $patientId,
                'status' => $status,
                'pending_attendance' => $pendingAttendance,
                'type' => $type,
                'start' => $start,
                'end' => $end,
                'limit' => $limit,
                'today' => now()->toDateString(),
            ],
            callback: fn () => $this->agenda->paginateByFilters(
                cols: ['id', 'user_id', 'patient_id', 'scheduled_on', 'ends_at', 'status', 'room_link', 'type'],
                userId: $userId,
                patientId: $patientId,
                status: $status,
                type: $type,
                start: $start,
                end: $end,
                perPage: $limit,
                pendingAttendance: $pendingAttendance,
            ),
        );
    }

    public function byPatient(int $patientId, int $userId, int $perPage, ?Carbon $start = null, ?Carbon $end = null, ?AgendaStatusEnum $status = null): LengthAwarePaginator
    {
        $cols = [
            'id',
            'patient_id',
            'scheduled_on',
            'ends_at',
            'status',
        ];

        return PaginateCache::remember(
            context: PaginateCacheContextEnum::AGENDA,
            userId: $userId,
            params: [
                'scope' => 'by_patient',
                'patient_id' => $patientId,
                'status' => $status,
                'start' => $start,
                'end' => $end,
                'limit' => $perPage,
                'today' => now()->toDateString(),
            ],
            callback: fn () => $this->agenda->byPatient(
                cols: $cols,
                userId: $userId,
                patientId: $patientId,
                start: $start,
                end: $end,
                perPage: $perPage,
                status: $status,
            ),
        );

        /*->map(function (Agenda $agendaItem) use ($timezone) {
            $scheduledOn = Carbon::parse($agendaItem->scheduled_on)->setTimezone($timezone);
            $endsAt = Carbon::parse($agendaItem->ends_at)->setTimezone($timezone);

            return [
                'id' => $agendaItem->id,
                'patient_id' => $agendaItem->patient_id,
                'scheduled_on' => $scheduledOn->toDateString(),
                'date_label' => $scheduledOn->format('d/m/Y'),
                'time_start' => $scheduledOn->format('H:i'),
                'time_end' => $endsAt->format('H:i'),
                'status' => $agendaItem->status,
            ];
        });*/
    }
}
