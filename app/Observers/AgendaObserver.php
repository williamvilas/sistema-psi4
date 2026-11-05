<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AgendaStatusEnum;
use App\Enums\NotificationTypeEnum;
use App\Enums\PaginateCacheContextEnum;
use App\Helpers\DashboardCache;
use App\Helpers\PaginateCache;
use App\Jobs\DeleteGoogleEventJob;
use App\Jobs\SendAgendaNotificationJob;
use App\Jobs\SyncAgendaToGoogleJob;
use App\Models\Agenda;
use App\Models\GoogleCalendarAccount;
use App\Models\Notifications;
use App\Services\AgendaReminderService;
use App\Services\AgendaVideoRoomService;

class AgendaObserver
{
    public function __construct(
        private readonly GoogleCalendarAccount $googleCalendarAccount,
        private readonly Notifications $notifications,
        private readonly AgendaReminderService $agendaReminderService,
        private readonly AgendaVideoRoomService $agendaVideoRoomService,
    ) {
    }

    /**
     * Calcula o momento do lembrete por WhatsApp ao criar, remarcar, trocar a modalidade ou reabrir a consulta.
     */
    public function saving(Agenda $agenda): void
    {
        if ($agenda->exists && ! $agenda->isDirty(['scheduled_on', 'type', 'status'])) {
            return;
        }

        if (! $agenda->status?->isOpen() || ! $agenda->scheduled_on) {
            $agenda->reminder_at = null;

            return;
        }

        // Remarcada para dentro da antecedência: o lembrete sai logo, com o novo horário
        $rescheduled = $agenda->exists && $agenda->isDirty('scheduled_on') && ! $agenda->isDirty('status');

        $agenda->reminder_at = $this->agendaReminderService->reminderAt(
            userId: (int) $agenda->user_id,
            type: (string) $agenda->type,
            scheduledOnUtc: $agenda->scheduled_on->copy()->utc(),
            sendLate: $rescheduled,
        );
    }

    public function created(Agenda $agenda): void
    {
        DashboardCache::forget($agenda->user_id);
        PaginateCache::forget($agenda->user_id, PaginateCacheContextEnum::AGENDA);
        $this->notifyStatus($agenda);
        $this->syncToGoogle($agenda);
    }

    public function updated(Agenda $agenda): void
    {
        DashboardCache::forget($agenda->user_id);
        PaginateCache::forget($agenda->user_id, PaginateCacheContextEnum::AGENDA);

        // Horário alterado ou consulta reaberta (registro cancelado reaproveitado)
        $reopened = $agenda->wasChanged('status') && $agenda->getOriginal('status') === AgendaStatusEnum::CANCELED;

        // Libera os avisos da consulta antiga para a nova (confirmação, lembrete e link) não serem barrados
        if ($reopened || $agenda->wasChanged('scheduled_on')) {
            $this->notifications->releaseByAgenda($agenda->id);
        }

        if ($agenda->wasChanged('status')) {
            $this->notifyStatus($agenda);
        }

        if ($agenda->status === AgendaStatusEnum::CANCELED) {
            $this->removeFromGoogle($agenda);
            $this->agendaVideoRoomService->cancel($agenda->id);

            return;
        }

        // A janela de acesso da sala do PSI4 acompanha o novo horário
        if ($agenda->wasChanged(['scheduled_on', 'ends_at'])) {
            $this->agendaVideoRoomService->reschedule($agenda);
        }

        if ($reopened || $agenda->wasChanged(['scheduled_on', 'ends_at'])) {
            $this->syncToGoogle($agenda);
        }
    }

    public function deleted(Agenda $agenda): void
    {
        DashboardCache::forget($agenda->user_id);
        PaginateCache::forget($agenda->user_id, PaginateCacheContextEnum::AGENDA);
        $this->agendaVideoRoomService->cancel($agenda->id);
    }

    public function restored(Agenda $agenda): void
    {
        DashboardCache::forget($agenda->user_id);
        PaginateCache::forget($agenda->user_id, PaginateCacheContextEnum::AGENDA);
    }

    public function forceDeleted(Agenda $agenda): void
    {
        DashboardCache::forget($agenda->user_id);
        PaginateCache::forget($agenda->user_id, PaginateCacheContextEnum::AGENDA);
    }

    private function notifyStatus(Agenda $agenda): void
    {
        if (NotificationTypeEnum::fromAgendaStatus($agenda->status) === null) {
            return;
        }

        SendAgendaNotificationJob::dispatch(agendaId: $agenda->id, status: $agenda->status);
    }

    private function syncToGoogle(Agenda $agenda): void
    {
        if ($this->googleCalendarAccount->isIntegratedByUser($agenda->user_id)) {
            SyncAgendaToGoogleJob::dispatch(agendaId: $agenda->id);
        }
    }

    private function removeFromGoogle(Agenda $agenda): void
    {
        $eventId = $agenda->google_event_id;

        if (! $eventId || ! $agenda->wasChanged('status')) {
            return;
        }

        // Zera o vínculo já: se o horário for reaproveitado por um novo agendamento, ele cria um evento novo
        $agenda->updateGoogleEventQuietly($agenda->id, ['google_event_id' => null, 'google_synced_at' => null]);

        DeleteGoogleEventJob::dispatch(userId: $agenda->user_id, eventId: $eventId);
    }
}
