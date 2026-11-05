<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Agenda;
use App\Models\User;
use Carbon\Carbon;

/**
 * Calcula quando sai o lembrete por WhatsApp de cada consulta (coluna `agenda.reminder_at`),
 * a partir da antecedência configurada para a modalidade e respeitando o horário de silêncio.
 */
readonly class AgendaReminderService
{
    private const DEFAULT_TIMEZONE = 'America/Sao_Paulo';

    // Lembrete muito em cima da hora perde o sentido (o link da sala sai 5 minutos antes)
    private const MIN_MINUTES_BEFORE_START = 10;

    public function __construct(
        private AgendaSettingService $agendaSettingService,
        private Agenda $agenda,
        private User $users,
    ) {
    }

    /**
     * Momento (UTC) do lembrete ou null quando não há lembrete a enviar.
     *
     * @param  bool  $sendLate  consulta remarcada (ou lembrete ainda não enviado): se a antecedência já passou,
     *                          o lembrete sai assim que possível em vez de ser descartado
     */
    public function reminderAt(int $userId, string $type, Carbon $scheduledOnUtc, bool $sendLate = false): ?Carbon
    {
        $minutes = $this->agendaSettingService->details($userId)?->reminderMinutesFor($type);
        $now = now();
        $latest = $scheduledOnUtc->copy()->subMinutes(self::MIN_MINUTES_BEFORE_START);

        if (! $minutes || $latest->lte($now)) {
            return null;
        }

        $timezone = $this->users->findTimezoneById($userId) ?: self::DEFAULT_TIMEZONE;
        $reminderAt = $this->beforeQuietHours($scheduledOnUtc->copy()->subMinutes($minutes), $timezone);

        if ($reminderAt->gt($now)) {
            return $reminderAt;
        }

        // Agendada já dentro da antecedência: a confirmação do agendamento faz o papel do lembrete
        if (! $sendLate) {
            return null;
        }

        $reminderAt = $this->afterQuietHours($now, $timezone);

        return $reminderAt->lte($latest) ? $reminderAt : null;
    }

    /**
     * Recalcula o lembrete das consultas futuras em aberto (antecedência alterada nas configurações).
     */
    public function refreshByUser(int $userId): int
    {
        $agendas = $this->agenda->listOpenFromByUser($userId, now(), ['id', 'type', 'scheduled_on', 'reminder_at']);

        foreach ($agendas as $agenda) {
            // reminder_at preenchido = lembrete ainda não enviado: com a nova antecedência já vencida, sai agora
            $reminderAt = $this->reminderAt($userId, $agenda->type, $agenda->scheduled_on, sendLate: $agenda->reminder_at !== null);

            $this->agenda->updateReminderAtQuietly($agenda->id, $reminderAt);
        }

        return $agendas->count();
    }

    /**
     * Lembrete que cairia no horário de silêncio é antecipado para o fim da noite anterior.
     */
    private function beforeQuietHours(Carbon $atUtc, string $timezone): Carbon
    {
        ['start' => $start, 'end' => $end, 'fallback' => $fallback] = config('whatsapp.quiet_hours');
        $local = $atUtc->copy()->setTimezone($timezone);

        if ($local->hour >= $start) {
            $local->setTime($fallback, 0);
        } elseif ($local->hour < $end) {
            $local->subDay()->setTime($fallback, 0);
        }

        return $local->utc();
    }

    /**
     * Envio atrasado no horário de silêncio fica para o fim do silêncio.
     */
    private function afterQuietHours(Carbon $atUtc, string $timezone): Carbon
    {
        ['start' => $start, 'end' => $end] = config('whatsapp.quiet_hours');
        $local = $atUtc->copy()->setTimezone($timezone);

        if ($local->hour >= $start) {
            $local->addDay()->setTime($end, 0);
        } elseif ($local->hour < $end) {
            $local->setTime($end, 0);
        }

        return $local->utc();
    }
}
