<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AgendaTypeEnum;
use App\Enums\AttendanceModeEnum;
use App\Enums\FeatureUsageEnum;
use App\Exceptions\PlanLimitException;
use App\Helpers\NumberHelper;
use App\Helpers\StringHelper;
use App\Helpers\UserHelper;
use App\Models\Agenda;
use App\Models\AgendaSettings;
use App\Models\Patients;
use App\Models\PsychologistServices;
use App\Models\PsychologistSettings;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

readonly class PublicBookingService
{
    private const DAY_LABELS = ['seg' => 'Seg', 'ter' => 'Ter', 'qua' => 'Qua', 'qui' => 'Qui', 'sex' => 'Sex', 'sab' => 'Sáb', 'dom' => 'Dom'];

    public function __construct(
        private User $users,
        private PsychologistServices $services,
        private Agenda $agenda,
        private Patients $patients,
        private AgendaSettingService $agendaSettingService,
        private PsychologistSettingService $psychologistSettingService,
        private BookingSettingService $bookingSettingService,
        private AgendaService $agendaService,
        private GoogleCalendarService $googleCalendarService,
        private NotificationService $notificationService,
        private PlanLimitService $planLimitService,
    ) {
    }

    /**
     * Retorna o contexto do psicólogo dono da página ou null quando a página não existe/está desativada.
     */
    public function findActive(string $slug): ?array
    {
        $user = $this->users->findBySlug($slug, ['id', 'name', 'email', 'whatsapp', 'crp', 'photo', 'timezone', 'current_plan']);

        if (! $user) {
            return null;
        }

        $booking = $this->bookingSettingService->details($user->id);

        if (! $booking || ! $booking->public_booking_enabled) {
            return null;
        }

        $agenda = $this->agendaSettingService->details($user->id);

        return [
            'user' => $user,
            'psychologist' => $this->psychologistSettingService->details($user->id),
            'agenda' => $agenda,
            'services' => $this->services->listByUser($user->id),
            'minutes' => $this->agendaService->sessionMinutes($agenda),
        ];
    }

    public function profile(array $context): array
    {
        /** @var User $user */
        $user = $context['user'];
        /** @var PsychologistSettings|null $settings */
        $settings = $context['psychologist'];

        /** @var AgendaSettings|null $agenda */
        $agenda = $context['agenda'];
        $mode = $agenda?->attendance_mode ?? AttendanceModeEnum::ONLINE;

        return [
            'name' => $user->name,
            'clinic_name' => $settings?->clinic_name,
            'crp' => $settings?->crp ?: $user->crp,
            'photo' => $settings?->photoUrl() ?? UserHelper::getPhoto($user),
            'address' => $settings?->addressLines() ?? [],
            'maps_url' => $settings?->mapsUrl(),
            'mode' => $mode->value,
            'in_person' => $mode->hasInPerson(),
            'phone' => $user->whatsapp,
            'email' => $user->email,
            'schedule' => $this->scheduleLines($context['agenda']),
            'minutes' => $context['minutes'],
            'services' => $context['services']->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'price' => $s->price,
            ])->all(),
        ];
    }

    /**
     * Dias com pelo menos um horário livre, já com os horários agrupados por modalidade.
     *
     * @return array<int, array{date: string, types: string[], slots: array<string, string[]>}>
     */
    public function availability(array $context): array
    {
        /** @var User $user */
        $user = $context['user'];

        // Limite de agendamentos do plano atingido: o paciente só vê que não há horários (o plano nunca aparece para ele)
        if ($this->planLimitService->hasReached($user->id, 'total_agenda')) {
            return [];
        }

        $tz = $user->timezone;
        $now = now($tz);
        $minStart = $now->copy()->addHours((int) config('booking.min_notice_hours'));
        $busy = $this->agendaService->busyKeys($user->id, $user->timezone, $now->copy()->startOfDay(), $now->copy()->addDays((int) config('booking.horizon_days'))->endOfDay());

        $days = [];

        for ($i = 0; $i <= (int) config('booking.horizon_days') && count($days) < (int) config('booking.max_days'); $i++) {
            $day = $now->copy()->addDays($i)->startOfDay();
            $slotTypes = $this->agendaService->freeSlotTypes($day, $context['agenda'], $context['minutes'], $busy, $minStart);

            if ($slotTypes) {
                $days[] = [
                    'date' => $day->toDateString(),
                    'types' => $this->agendaService->typesOf($slotTypes),
                    // Cada período tem sua modalidade: o paciente escolhe a modalidade e vê só os horários dela
                    'slots' => $this->agendaService->slotsByType($slotTypes),
                ];
            }
        }

        return $days;
    }

    /**
     * @return array{name: string, type: string, room_link: ?string, address: ?string, service: string, price: string, date: string, time: string}
     */
    public function book(string $slug, array $data): array
    {
        $context = $this->findActive($slug);

        if (! $context) {
            throw new \DomainException(__('public-booking.not_found'));
        }

        /** @var User $user */
        $user = $context['user'];
        $service = $this->services->findByUser((int) $data['service_id'], $user->id);

        if (! $service) {
            throw new \DomainException(__('public-booking.service_not_found'));
        }

        $this->assertSlotIsFree($context, $data['date'], $data['time'], $data['type']);

        try {
            DB::transaction(function () use ($user, $service, $data, $context) {
                $this->planLimitService->consume(userId: $user->id, feature: FeatureUsageEnum::PUBLIC_BOOKING);

                $patientId = $this->resolvePatient($user, $data);

                $this->agendaService->store(
                    data: [
                        'patient_id' => $patientId,
                        'service_id' => $service->id,
                        'type' => $data['type'],
                        'date' => $data['date'],
                        'timer' => [$data['time']],
                        'price' => $service->price,
                        'duration' => $context['minutes'],
                        'notes' => __('public-booking.agenda_note'),
                    ],
                    userId: $user->id,
                    timezone: $user->timezone,
                );
            });
        } catch (PlanLimitException) {
            throw new \DomainException(__('public-booking.online_unavailable'));
        }

        $scheduledOnUtc = Carbon::createFromFormat('Y-m-d H:i', $data['date'].' '.$data['time'], $user->timezone)->utc();

        // A psicóloga só é avisada dos agendamentos que não foi ela que fez
        $agendaId = $this->agenda->findIdAt($user->id, $scheduledOnUtc);

        if ($agendaId !== null) {
            $this->notificationService->notifyPublicBooking($agendaId);
        }
        $online = $data['type'] === AgendaTypeEnum::ONLINE->value;

        return [
            'name' => $data['name'],
            'type' => $data['type'],
            'room_link' => $online ? $this->agenda->roomLinkAt($user->id, $scheduledOnUtc) : null,
            'address' => $online ? null : $context['psychologist']?->fullAddress(),
            'maps_url' => $online ? null : $context['psychologist']?->mapsUrl(),
            'service' => $service->name,
            'price' => (string) $service->price,
            'date' => $data['date'],
            'time' => $data['time'],
        ];
    }

    private function assertSlotIsFree(array $context, string $date, string $time, string $type): void
    {
        /** @var User $user */
        $user = $context['user'];
        $tz = $user->timezone;
        $now = now($tz);
        $day = Carbon::createFromFormat('Y-m-d', $date, $tz)->startOfDay();

        if ($day->lt($now->copy()->startOfDay()) || $day->gt($now->copy()->addDays((int) config('booking.horizon_days'))->endOfDay())) {
            throw new \DomainException(__('public-booking.unavailable'));
        }

        // Sem cache: o horário só é confirmado com o que o Google diz agora
        $busy = $this->agendaService->busyKeys($user->id, $user->timezone, $day->copy(), $day->copy()->endOfDay(), fresh: true);
        $minStart = $now->copy()->addHours((int) config('booking.min_notice_hours'));
        $slots = $this->agendaService->freeSlotTypes($day, $context['agenda'], $context['minutes'], $busy, $minStart);

        if (! isset($slots[$time])) {
            // A listagem estava desatualizada: força a próxima a consultar o Google de novo
            $this->googleCalendarService->flushBusyCache($user->id);

            throw new \DomainException(__('public-booking.unavailable'));
        }

        // Horário livre, mas de um período com outra modalidade (ex.: presencial num período online)
        if (! in_array($type, $slots[$time], true)) {
            throw new \DomainException(__('public-booking.mode_not_allowed'));
        }
    }

    /**
     * Reaproveita o paciente do psicólogo com mesmo telefone e nome semelhante; caso contrário cadastra um novo.
     */
    private function resolvePatient(User $user, array $data): int
    {
        $phone = NumberHelper::onlyNumbers($data['phone']);
        $document = $data['document'] ?? null;

        // A página é pública: paciente encontrado nunca tem os dados alterados, senão quem conhece
        // o nome e o telefone trocaria o CPF de entrada na sala e o pagador do recibo
        $patient = $document ? $this->patients->getPatient($document, $user->id) : null;

        if ($patient) {
            return $patient->id;
        }

        foreach ($this->patients->getAllByPhone($phone, $user->id, ['id', 'name']) as $patient) {
            if (StringHelper::namesAreSimilar($patient->name, $data['name'])) {
                return $patient->id;
            }
        }

        return $this->patients->create([
            'user_id' => $user->id,
            'name' => StringHelper::firstLetterCapitalized($data['name']),
            'phone' => $phone,
            'document' => $document,
            'payer_name' => $data['payer_name'] ?? null,
            'payer_document' => $data['payer_document'] ?? null,
        ])->id;
    }

    /**
     * Agrupa dias consecutivos com o mesmo horário (ex.: "Seg a Sex · 8h às 18h").
     *
     * @return string[]
     */
    private function scheduleLines(?AgendaSettings $settings): array
    {
        if (! $settings) {
            return [];
        }

        $groups = [];
        $showMode = ($settings->attendance_mode ?? AttendanceModeEnum::ONLINE) === AttendanceModeEnum::BOTH;

        foreach (self::DAY_LABELS as $key => $label) {
            $periods = $settings->periodsForDay($key);

            if (empty($settings->agenda_days[$key]['enabled']) || $periods === []) {
                continue;
            }

            // Ex.: "8h às 12h (presencial), 14h às 19h (online)"; a modalidade só aparece quando o período a restringe
            $range = implode(', ', array_map(function (array $period) use ($showMode) {
                $text = $this->formatHour($period['start']).' às '.$this->formatHour($period['end']);

                return $showMode && $period['mode'] !== AttendanceModeEnum::BOTH
                    ? $text.' ('.mb_strtolower(__('agenda.day_mode.'.$period['mode']->value)).')'
                    : $text;
            }, $periods));
            $last = array_key_last($groups);

            if ($last !== null && $groups[$last]['range'] === $range && $groups[$last]['consecutive'] === $key) {
                $groups[$last]['days'][] = $label;
            } else {
                $groups[] = ['range' => $range, 'days' => [$label], 'consecutive' => null];
                $last = array_key_last($groups);
            }

            $keys = array_keys(self::DAY_LABELS);
            $groups[$last]['consecutive'] = $keys[array_search($key, $keys, true) + 1] ?? null;
        }

        return array_map(function (array $group) {
            $days = $group['days'];
            $text = match (true) {
                count($days) === 1 => $days[0],
                count($days) === 2 => $days[0].' e '.$days[1],
                default => $days[0].' a '.end($days),
            };

            return $text.' · '.$group['range'];
        }, $groups);
    }

    private function formatHour(string $time): string
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '00');

        return (int) $h.'h'.((int) $m > 0 ? $m : '');
    }
}
