<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AgendaStatusEnum;
use App\Enums\AgendaTypeEnum;
use App\Enums\NotificationChannelEnum;
use App\Enums\NotificationRecipientEnum;
use App\Enums\NotificationTypeEnum;
use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\Agenda;
use App\Models\Notifications;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

readonly class NotificationService
{
    private const DEFAULT_TIMEZONE = 'America/Sao_Paulo';

    private const AGENDA_COLS = ['id', 'user_id', 'patient_id', 'type', 'status', 'scheduled_on', 'room_link'];

    public function __construct(private Agenda $agenda, private Notifications $notifications)
    {
    }

    /**
     * Registra no histórico as notificações do novo status da agenda.
     * Só os tipos com modelo de WhatsApp configurado são enviados; os demais ficam registrados como pendentes.
     *
     * @return int total de notificações registradas (ignora as já existentes)
     */
    public function notifyAgendaStatus(int $agendaId, AgendaStatusEnum $status): int
    {
        $type = NotificationTypeEnum::fromAgendaStatus($status);

        if ($type === null) {
            return 0;
        }

        $agenda = $this->findNotifiableAgenda($agendaId);

        if (! $agenda) {
            return 0;
        }

        $total = 0;

        foreach (NotificationRecipientEnum::forAgendaStatus($status) as $recipient) {
            $total += (int) $this->register($agenda, $recipient, $type);
        }

        return $total;
    }

    /**
     * Avisa a psicóloga de um agendamento feito pelo paciente na página pública.
     */
    public function notifyPublicBooking(int $agendaId): bool
    {
        $agenda = $this->findNotifiableAgenda($agendaId);

        return $agenda !== null && $this->register($agenda, NotificationRecipientEnum::PSYCHOLOGIST, NotificationTypeEnum::AGENDA_SCHEDULED);
    }

    /**
     * Lembrete ao paciente com os botões "Confirmo presença" / "Preciso remarcar".
     */
    public function notifyAgendaReminder(int $agendaId): bool
    {
        $agenda = $this->findNotifiableAgenda($agendaId);

        if (! $agenda || ! $agenda->status->isOpen()) {
            return false;
        }

        return $this->register($agenda, NotificationRecipientEnum::PATIENT, NotificationTypeEnum::AGENDA_REMINDER);
    }

    /**
     * Link da sala para o paciente e a psicóloga pouco antes da consulta online. Sem link ainda (Meet não gerado),
     * só a psicóloga é avisada para adicionar o link; o envio é tentado de novo enquanto a consulta não começa.
     *
     * @return int total de notificações registradas
     */
    public function notifyRoomLink(int $agendaId): int
    {
        $agenda = $this->findNotifiableAgenda($agendaId);

        if (! $agenda || ! $agenda->status->isOpen() || $agenda->type !== AgendaTypeEnum::ONLINE->value) {
            return 0;
        }

        if (empty($agenda->room_link)) {
            return (int) $this->register($agenda, NotificationRecipientEnum::PSYCHOLOGIST, NotificationTypeEnum::AGENDA_MEET_MISSING);
        }

        return (int) $this->register($agenda, NotificationRecipientEnum::PATIENT, NotificationTypeEnum::AGENDA_ROOM_LINK)
            + (int) $this->register($agenda, NotificationRecipientEnum::PSYCHOLOGIST, NotificationTypeEnum::AGENDA_ROOM_LINK);
    }

    /**
     * Paciente tocou em "Preciso remarcar" no lembrete: a psicóloga é avisada para combinar outro horário.
     */
    public function notifyRescheduleRequest(int $agendaId): bool
    {
        $agenda = $this->findNotifiableAgenda($agendaId);

        return $agenda !== null && $this->register($agenda, NotificationRecipientEnum::PSYCHOLOGIST, NotificationTypeEnum::AGENDA_RESCHEDULE_REQUEST);
    }

    /**
     * Avisa o psicólogo de que a consulta já passou e falta informar se o paciente compareceu ou faltou.
     *
     * @return bool true quando a notificação foi registrada (false se já existia ou não há contato)
     */
    public function notifyAgendaAttendancePending(int $agendaId): bool
    {
        $agenda = $this->agenda->findByIdWithPatientAndUser(id: $agendaId, cols: self::AGENDA_COLS);

        if (! $agenda || ! $agenda->patient || ! $agenda->user) {
            return false;
        }

        return $this->register($agenda, NotificationRecipientEnum::PSYCHOLOGIST, NotificationTypeEnum::AGENDA_ATTENDANCE_PENDING);
    }

    /**
     * Variáveis do texto da notificação (as mesmas dos modelos de WhatsApp, ver config/whatsapp.php).
     * O paciente é tratado pelo primeiro nome; a psicóloga vê o nome completo do paciente.
     *
     * @return array{patient: string, psychologist: string, date: string, link: string}
     */
    public function messageParams(Agenda $agenda, NotificationRecipientEnum $recipient): array
    {
        $timezone = $agenda->user->timezone ?: self::DEFAULT_TIMEZONE;
        $patientName = trim((string) $agenda->patient->name);

        return [
            'patient' => $recipient === NotificationRecipientEnum::PATIENT
                ? Str::title(Str::before($patientName, ' '))
                : $patientName,
            'psychologist' => trim((string) $agenda->user->name),
            'date' => $agenda->scheduled_on->copy()->setTimezone($timezone)->format('d/m/Y \à\s H:i'),
            'link' => (string) $agenda->room_link,
        ];
    }

    /**
     * Agenda com paciente e psicóloga.
     */
    private function findNotifiableAgenda(int $agendaId): ?Agenda
    {
        $agenda = $this->agenda->findByIdWithPatientAndUser(id: $agendaId, cols: self::AGENDA_COLS);

        if (! $agenda || ! $agenda->patient || ! $agenda->user) {
            return null;
        }

        return $agenda;
    }

    private function register(Agenda $agenda, NotificationRecipientEnum $recipient, NotificationTypeEnum $type): bool
    {
        $channel = $this->decideChannel($agenda, $recipient);

        if ($channel === null) {
            return false;
        }

        $notification = $this->notifications->createPendingIfNotExists(
            userId: $agenda->user_id,
            patientId: $agenda->patient_id,
            agendaId: $agenda->id,
            recipient: $recipient,
            type: $type,
            channel: $channel,
            message: __("notifications.{$recipient->value}.{$type->value}", $this->messageParams($agenda, $recipient)),
        );

        if ($notification === null) {
            return false;
        }

        Log::info('Notificação da agenda registrada.', [
            'notification_id' => $notification->id,
            'agenda_id' => $agenda->id,
            'recipient' => $recipient->value,
            'type' => $type->value,
            'channel' => $channel->value,
        ]);

        if ($channel === NotificationChannelEnum::WHATSAPP && $this->sendsByWhatsApp($recipient, $type)) {
            SendWhatsAppNotificationJob::dispatch(notificationId: $notification->id);
        }

        return true;
    }

    private function sendsByWhatsApp(NotificationRecipientEnum $recipient, NotificationTypeEnum $type): bool
    {
        return config('whatsapp.enabled') && config("whatsapp.templates.{$recipient->value}.{$type->value}") !== null;
    }

    /**
     * WhatsApp quando há telefone (e o paciente não saiu dos avisos automáticos); senão e-mail, que por enquanto só é registrado.
     */
    private function decideChannel(Agenda $agenda, NotificationRecipientEnum $recipient): ?NotificationChannelEnum
    {
        [$phone, $email, $whatsAppAllowed] = $recipient === NotificationRecipientEnum::PATIENT
            ? [$agenda->patient->phone, $agenda->patient->email, $agenda->patient->whatsapp_notifications !== false]
            : [$agenda->user->whatsapp, $agenda->user->email, true];

        return match (true) {
            ! empty($phone) && $whatsAppAllowed => NotificationChannelEnum::WHATSAPP,
            ! empty($email) => NotificationChannelEnum::EMAIL,
            default => null,
        };
    }
}
