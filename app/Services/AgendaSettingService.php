<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttendanceModeEnum;
use App\Helpers\CacheHelper;
use App\Jobs\RefreshAgendaRemindersJob;
use App\Models\AgendaHolidays;
use App\Models\AgendaSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

readonly class AgendaSettingService
{
    private const REMINDER_FIELDS = ['reminder_in_person_minutes', 'reminder_online_minutes'];

    public const DEFAULT_SEND_LINK_TEXT = 'Já está quase na hora da nossa sessão! Quando puder, é só clicar no link abaixo, sem precisar de cadastro. Vou te esperar por lá!';

    public function __construct(
        private AgendaSettings $agendaSettings,
        private AgendaHolidays $holidays,
    ) {
    }

    public function details(int $userId): ?AgendaSettings
    {
        $key = $this->cacheKey($userId);
        $settings = CacheHelper::get(key: $key);

        if ($settings) {
            return $settings;
        }

        $settings = $this->agendaSettings->findWithHolidaysByUser($userId);

        if ($settings) {
            CacheHelper::put(key: $key, value: $settings, ttl: now()->addMinutes(5));
        }

        return $settings;
    }

    public function store(array $data, int $userId): AgendaSettings
    {
        $settings = DB::transaction(function () use ($data, $userId) {
            $this->holidays->replaceByUser($userId, $data['holidays']);

            return $this->agendaSettings->saveByUser($userId, [
                'consultation_time' => $data['consultation_time'],
                'available_time_minutes' => $data['available_time_minutes'],
                'attendance_mode' => $data['attendance_mode'] ?? AttendanceModeEnum::ONLINE->value,
                'agenda_days' => $data['agenda_days'],
                'lunch_enabled' => $data['lunch_enabled'],
                'lunch_start' => $data['lunch_start'],
                'lunch_end' => $data['lunch_end'],
                'dinner_enabled' => $data['dinner_enabled'] ?? false,
                'dinner_start' => $data['dinner_start'] ?? null,
                'dinner_end' => $data['dinner_end'] ?? null,
                'send_link_message' => $data['send_link_message'] ?? null,
            ] + array_intersect_key($data, array_flip(self::REMINDER_FIELDS)));
        });

        CacheHelper::forget($this->cacheKey($userId));

        // Antecedência dos lembretes alterada: recalcula o lembrete das consultas já marcadas
        if (! $settings->wasRecentlyCreated && $settings->wasChanged(self::REMINDER_FIELDS)) {
            RefreshAgendaRemindersJob::dispatch(userId: $userId);
        }

        return $settings;
    }

    public function sendLinkText(int $userId): string
    {
        $text = trim((string) $this->details($userId)?->send_link_message);

        return $text !== '' ? $text : self::DEFAULT_SEND_LINK_TEXT;
    }

    public function buildSendLinkMessage(string $text, string $patientName, Carbon $scheduledOn, string $roomLink): string
    {
        $firstName = Str::title(Str::before(trim($patientName), ' '));

        $message = "Oi {$firstName}.\n{$text}\n\n"
            ."*Link:* {$roomLink}\n"
            .'*Data:* '.formatDateByAgenda($scheduledOn)."\n"
            .'*Horário:* '.$scheduledOn->format('H:i');

        // Na sala do PSI4 o paciente entra informando o CPF ou, sem ele, o telefone cadastrado
        if (str_starts_with($roomLink, config('psi_video.jitsi_base_url').'/')) {
            $message .= "\n\n".__('video-room.enter_with_cpf');
        }

        return $message;
    }

    private function cacheKey(int $userId): string
    {
        return "agenda_settings_user_{$userId}";
    }
}
