<?php

declare(strict_types=1);

namespace App\Http\Requests\AgendaSettings;

use App\Enums\AttendanceModeEnum;
use App\Helpers\DateTimeHelper;
use App\Services\PsychologistSettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    // Períodos por dia (ex.: manhã presencial, tarde online, noite online)
    private const MAX_PERIODS = 3;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $holidays = collect($this->input('holidays', []))
            ->map(function ($holiday) {
                if (is_array($holiday) && ! empty($holiday['date'])) {
                    $holiday['date'] = DateTimeHelper::convertToDatabase($holiday['date'], 'Y-m-d') ?? $holiday['date'];
                }

                return $holiday;
            })
            ->all();

        $this->merge(['holidays' => $holidays]);
    }

    public function rules(): array
    {
        return [
            'consultation_time' => ['nullable', 'date_format:H:i'],
            'available_time_minutes' => ['nullable', 'date_format:H:i'],
            'attendance_mode' => ['nullable', Rule::enum(AttendanceModeEnum::class)],
            'agenda_days' => ['nullable', 'array'],
            'agenda_days.*.enabled' => ['nullable', 'boolean'],
            'agenda_days.*.start' => ['nullable', 'date_format:H:i'],
            'agenda_days.*.end' => ['nullable', 'date_format:H:i'],
            'agenda_days.*.periods' => ['nullable', 'array', 'max:'.self::MAX_PERIODS],
            'agenda_days.*.periods.*.start' => ['nullable', 'date_format:H:i'],
            'agenda_days.*.periods.*.end' => ['nullable', 'date_format:H:i'],
            'agenda_days.*.periods.*.mode' => ['nullable', Rule::enum(AttendanceModeEnum::class)],
            'lunch_enabled' => ['nullable', 'boolean'],
            'lunch_start' => ['nullable', 'date_format:H:i'],
            'lunch_end' => ['nullable', 'date_format:H:i'],
            'dinner_enabled' => ['nullable', 'boolean'],
            'dinner_start' => ['nullable', 'required_if:dinner_enabled,1', 'date_format:H:i'],
            'dinner_end' => ['nullable', 'required_if:dinner_enabled,1', 'date_format:H:i', 'after:dinner_start'],
            'send_link_message' => ['nullable', 'string', 'max:500'],
            'reminder_in_person_minutes' => ['nullable', 'integer', Rule::in((array) config('whatsapp.reminder_options'))],
            'reminder_online_minutes' => ['nullable', 'integer', Rule::in((array) config('whatsapp.reminder_options'))],
            'holidays' => ['nullable', 'array'],
            'holidays.*.date' => ['nullable', 'date_format:Y-m-d'],
            'holidays.*.name' => ['nullable', 'string', 'max:255'],
            'holidays.*.work_enabled' => ['nullable', 'boolean'],
            'holidays.*.start' => ['nullable', 'date_format:H:i'],
            'holidays.*.end' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateTimeRanges($validator);
            $this->validatePeriodsOverlap($validator);
            $this->validateAddressForInPerson($validator);

            if (! $this->boolean('dinner_enabled') || ! $this->boolean('lunch_enabled')) {
                return;
            }

            [$lunchStart, $lunchEnd] = [$this->input('lunch_start'), $this->input('lunch_end')];
            [$dinnerStart, $dinnerEnd] = [$this->input('dinner_start'), $this->input('dinner_end')];

            if ($lunchStart && $lunchEnd && $dinnerStart && $dinnerEnd && $dinnerStart < $lunchEnd && $dinnerEnd > $lunchStart) {
                $validator->errors()->add('dinner_start', 'O horário de janta não pode sobrepor o horário de almoço.');
            }
        });
    }

    /**
     * Nos dias ativos, no almoço e nos feriados com atendimento, o fim precisa ser depois do início.
     */
    private function validateTimeRanges($validator): void
    {
        $ranges = [];

        foreach ($this->enabledDays() as $day => $hours) {
            if (! empty($hours['periods']) && is_array($hours['periods'])) {
                foreach ($hours['periods'] as $i => $period) {
                    $ranges["agenda_days.{$day}.periods.{$i}.end"] = [$period['start'] ?? null, $period['end'] ?? null];
                }

                continue;
            }

            $ranges["agenda_days.{$day}.end"] = [$hours['start'] ?? null, $hours['end'] ?? null];
        }

        if ($this->boolean('lunch_enabled')) {
            $ranges['lunch_end'] = [$this->input('lunch_start'), $this->input('lunch_end')];
        }

        foreach ((array) $this->input('holidays', []) as $id => $holiday) {
            if (is_array($holiday) && filter_var($holiday['work_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $ranges["holidays.{$id}.end"] = [$holiday['start'] ?? null, $holiday['end'] ?? null];
            }
        }

        foreach ($ranges as $field => [$start, $end]) {
            if ($this->isTime($start) && $this->isTime($end) && $start >= $end) {
                $validator->errors()->add($field, __('agenda.end_before_start'));
            }
        }
    }

    /**
     * Os períodos de um mesmo dia não podem se sobrepor: para o mesmo horário valer nas duas modalidades, usa-se "Os dois".
     */
    private function validatePeriodsOverlap($validator): void
    {
        foreach ($this->enabledDays() as $day => $hours) {
            $periods = collect((array) ($hours['periods'] ?? []))
                ->filter(fn ($period) => is_array($period) && $this->isTime($period['start'] ?? null) && $this->isTime($period['end'] ?? null))
                ->sortBy('start');

            $previousEnd = null;

            foreach ($periods as $i => $period) {
                if ($previousEnd !== null && $period['start'] < $previousEnd) {
                    $validator->errors()->add("agenda_days.{$day}.periods.{$i}.start", __('agenda.periods_overlap'));
                }

                $previousEnd = max($previousEnd ?? $period['end'], $period['end']);
            }
        }
    }

    /**
     * @return array<string, array> dias da semana com atendimento ligado
     */
    private function enabledDays(): array
    {
        return array_filter(
            (array) $this->input('agenda_days', []),
            fn ($hours) => is_array($hours) && filter_var($hours['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)
        );
    }

    /**
     * Atendimento presencial exige o endereço do consultório. O onboarding não pede
     * endereço: a psicóloga o completa depois em Configurações › Psicólogo.
     */
    private function validateAddressForInPerson($validator): void
    {
        if (! $this->attendanceMode()->hasInPerson() || $this->routeIs('onboarding.*')) {
            return;
        }

        if (! app(PsychologistSettingService::class)->details(Auth::id())?->hasAddress()) {
            $validator->errors()->add('attendance_mode', __('agenda.address_required'));
        }
    }

    private function attendanceMode(): AttendanceModeEnum
    {
        return AttendanceModeEnum::tryFrom((string) $this->input('attendance_mode')) ?? AttendanceModeEnum::ONLINE;
    }

    private function isTime(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{2}:\d{2}$/', $value) === 1;
    }

    /**
     * Os períodos ficam em ordem e o `start`/`end` do dia passa a ser do primeiro ao último período
     * (o onboarding envia só `start`/`end`). A modalidade só vale com "online e presencial";
     * nas demais, todos os períodos seguem a modalidade geral.
     */
    private function agendaDays(): array
    {
        $both = $this->attendanceMode() === AttendanceModeEnum::BOTH;

        return collect((array) $this->input('agenda_days', []))
            ->map(function ($day) use ($both) {
                if (! is_array($day)) {
                    return $day;
                }

                unset($day['mode']);

                if (empty($day['periods']) || ! is_array($day['periods'])) {
                    unset($day['periods']);

                    return $day;
                }

                $periods = collect($day['periods'])
                    ->filter(fn ($period) => is_array($period) && ! empty($period['start']) && ! empty($period['end']))
                    ->sortBy('start')
                    ->map(fn (array $period) => array_filter([
                        'start' => $period['start'],
                        'end' => $period['end'],
                        'mode' => $both ? ($period['mode'] ?? AttendanceModeEnum::BOTH->value) : null,
                    ]))
                    ->values();

                $day['periods'] = $periods->all();
                $day['start'] = $periods->first()['start'] ?? ($day['start'] ?? null);
                $day['end'] = $periods->max('end') ?? ($day['end'] ?? null);

                return $day;
            })
            ->all();
    }

    public function toPayload(): array
    {
        // O onboarding não envia a antecedência dos lembretes: sem o campo, vale o padrão da tabela
        $reminders = [];

        foreach (['reminder_in_person_minutes', 'reminder_online_minutes'] as $field) {
            if ($this->has($field)) {
                $reminders[$field] = $this->filled($field) ? $this->integer($field) : null;
            }
        }

        return $reminders + [
            'consultation_time' => $this->input('consultation_time') ?: '00:50',
            'available_time_minutes' => $this->input('available_time_minutes') ?: '00:10',
            'attendance_mode' => $this->attendanceMode()->value,
            'agenda_days' => $this->agendaDays(),
            'lunch_enabled' => $this->boolean('lunch_enabled'),
            'lunch_start' => $this->input('lunch_start'),
            'lunch_end' => $this->input('lunch_end'),
            'dinner_enabled' => $this->boolean('dinner_enabled'),
            'dinner_start' => $this->input('dinner_start'),
            'dinner_end' => $this->input('dinner_end'),
            'send_link_message' => trim((string) $this->input('send_link_message')) ?: null,
            'holidays' => collect($this->input('holidays', []))
                ->filter(fn ($holiday) => is_array($holiday) && ! empty($holiday['date']))
                ->map(fn (array $holiday) => [
                    'date' => $holiday['date'],
                    'name' => $holiday['name'] ?? null,
                    'work_enabled' => filter_var($holiday['work_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'start' => ($holiday['start'] ?? null) ?: null,
                    'end' => ($holiday['end'] ?? null) ?: null,
                ])
                ->values()
                ->all(),
        ];
    }
}
