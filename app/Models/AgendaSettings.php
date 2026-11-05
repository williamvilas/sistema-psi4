<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AgendaTypeEnum;
use App\Enums\AttendanceModeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgendaSettings extends Model
{
    protected $table = 'agenda_settings';

    protected $fillable = [
        'user_id',
        'consultation_time',
        'available_time_minutes',
        'attendance_mode',
        'agenda_days',
        'lunch_enabled',
        'lunch_start',
        'lunch_end',
        'dinner_enabled',
        'dinner_start',
        'dinner_end',
        'send_link_message',
        'reminder_in_person_minutes',
        'reminder_online_minutes',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'attendance_mode' => AttendanceModeEnum::class,
        'agenda_days' => 'array',
        'lunch_enabled' => 'boolean',
        'dinner_enabled' => 'boolean',
        'reminder_in_person_minutes' => 'integer',
        'reminder_online_minutes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(AgendaHolidays::class, 'user_id', 'user_id')->orderBy('date');
    }

    /**
     * Períodos de atendimento do dia, cada um com sua modalidade. Dia salvo sem `periods`
     * (onboarding ou configuração antiga) vira um período único com o `start`/`end` do dia.
     * Com "os dois" na configuração geral, cada período pode restringir a online ou presencial.
     *
     * @return array<int, array{start: string, end: string, mode: AttendanceModeEnum}>
     */
    public function periodsForDay(string $dayKey): array
    {
        $config = $this->agenda_days[$dayKey] ?? [];
        $general = $this->attendance_mode ?? AttendanceModeEnum::ONLINE;

        $periods = ! empty($config['periods']) && is_array($config['periods'])
            ? $config['periods']
            : [['start' => $config['start'] ?? null, 'end' => $config['end'] ?? null]];

        $result = [];

        foreach ($periods as $period) {
            if (empty($period['start']) || empty($period['end'])) {
                continue;
            }

            $result[] = [
                'start' => (string) $period['start'],
                'end' => (string) $period['end'],
                'mode' => $general !== AttendanceModeEnum::BOTH
                    ? $general
                    : (AttendanceModeEnum::tryFrom((string) ($period['mode'] ?? '')) ?? AttendanceModeEnum::BOTH),
            ];
        }

        return $result;
    }

    /**
     * Antecedência (minutos) do lembrete por WhatsApp da modalidade da consulta; null quando desligado.
     */
    public function reminderMinutesFor(string $agendaType): ?int
    {
        return $agendaType === AgendaTypeEnum::ONLINE->value
            ? $this->reminder_online_minutes
            : $this->reminder_in_person_minutes;
    }

    public function findByUser(int $userId, array $cols = ['*']): ?self
    {
        return self::query()->select($cols)->where('user_id', $userId)->first();
    }

    public function findWithHolidaysByUser(int $userId): ?self
    {
        return self::query()->with('holidays')->where('user_id', $userId)->first();
    }

    public function saveByUser(int $userId, array $data): self
    {
        return self::query()->updateOrCreate(['user_id' => $userId], $data);
    }
}
