<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AgendaStatusEnum;
use App\Enums\AgendaTypeEnum;
use App\Enums\PaginateCacheContextEnum;
use App\Helpers\PaginateCache;
use App\Jobs\SyncAgendaToGoogleJob;
use App\Models\Agenda;
use App\Models\GoogleCalendarAccount;
use App\Wrappers\GoogleCalendarWrapper;
use Illuminate\Support\Str;

readonly class GoogleCalendarSyncService
{
    private const AGENDA_COLUMNS = [
        'id', 'user_id', 'patient_id', 'type', 'scheduled_on', 'ends_at',
        'status', 'room_link', 'google_event_id', 'google_synced_at',
    ];

    public function __construct(
        private Agenda $agenda,
        private GoogleCalendarAccount $account,
        private GoogleCalendarService $googleCalendarService,
        private GoogleCalendarWrapper $wrapper,
        private AgendaVideoRoomService $agendaVideoRoomService,
        private PsychologistSettingService $psychologistSettingService,
    ) {
    }

    /**
     * Espelha a consulta na agenda Google do psicólogo (cria ou atualiza o evento).
     * A consulta online já nasce com a sala do PSI4 (ou link manual), enviada como local do evento.
     * Consulta online que ainda estiver sem link ganha a sala do PSI4 e o evento é reenviado com ela.
     *
     * Recusas definitivas (conta desconectada, acesso revogado, 4xx) não geram
     * nova tentativa; falhas transitórias são relançadas para o job tentar de novo.
     */
    public function syncAgenda(int $agendaId): void
    {
        try {
            $this->upsertEvent($agendaId);
        } catch (\DomainException) {
            $this->fallbackToVideoRoom($agendaId);
        }
    }

    /**
     * Consulta online que ficou sem link recebe uma sala do PSI4. Se o evento já existe
     * no Google, ele é reenviado para levar o link como local.
     */
    public function fallbackToVideoRoom(int $agendaId): void
    {
        $agenda = $this->agenda->findByIdForGoogleSync($agendaId, self::AGENDA_COLUMNS);

        if (! $agenda || $agenda->status === AgendaStatusEnum::CANCELED || ! $this->needsRoom($agenda)) {
            return;
        }

        if (! $this->agendaVideoRoomService->attach($agenda->id)) {
            return;
        }

        // A gravação é silenciosa (sem observer): a listagem precisa mostrar o novo link
        PaginateCache::forget($agenda->user_id, PaginateCacheContextEnum::AGENDA);

        if ($agenda->google_synced_at !== null) {
            SyncAgendaToGoogleJob::dispatch(agendaId: $agenda->id);
        }
    }

    /**
     * Remove o evento da agenda Google (consulta cancelada). Sem conta
     * conectada não há o que remover.
     */
    public function removeEvent(int $userId, string $eventId): void
    {
        $account = $this->account->findIntegratedByUser($userId);

        if (! $account) {
            return;
        }

        try {
            $this->wrapper->deleteEvent(
                accessToken: $this->googleCalendarService->getValidAccessToken($account),
                calendarId: $account->calendar_id,
                eventId: $eventId,
                notifyAttendees: $account->invite_patient,
            );
        } catch (\DomainException) {
            // Recusa definitiva: nada a fazer, o evento fica como está no Google
        }
    }

    private function upsertEvent(int $agendaId): void
    {
        $agenda = $this->agenda->findByIdForGoogleSync($agendaId, self::AGENDA_COLUMNS);

        // Cancelada/apagada entre o agendamento e o processamento do job
        if (! $agenda || $agenda->status === AgendaStatusEnum::CANCELED) {
            return;
        }

        $account = $this->account->findIntegratedByUser($agenda->user_id);

        if (! $account) {
            throw new \DomainException(__('google-calendar.not_connected'));
        }

        $accessToken = $this->googleCalendarService->getValidAccessToken($account);
        $event = $this->buildEvent($agenda, $account);

        // O id é gravado antes da chamada: se o reenvio ocorrer após um sucesso cuja resposta
        // se perdeu, o Google responde 409 e o evento é apenas atualizado, sem duplicar
        $eventId = $agenda->google_event_id ?: $this->newEventId($agenda);

        if ($agenda->google_event_id === null) {
            $this->agenda->updateGoogleEventQuietly($agenda->id, ['google_event_id' => $eventId]);
        }

        $result = $agenda->google_synced_at === null
            ? $this->createOrPatch($accessToken, $account, $eventId, $event)
            : $this->patchOrRecreate($accessToken, $account, $agenda, $eventId, $event);

        $this->agenda->updateGoogleEventQuietly($agenda->id, ['google_event_id' => $result['id'], 'google_synced_at' => now()]);

        // Consulta online ainda sem link: ganha a sala do PSI4 e o evento é reenviado com ela
        if ($this->needsRoom($agenda)) {
            $this->fallbackToVideoRoom($agenda->id);
        }
    }

    /**
     * @return array{id: string}
     */
    private function createOrPatch(string $accessToken, GoogleCalendarAccount $account, string $eventId, array $event): array
    {
        $result = $this->wrapper->insertEvent(
            accessToken: $accessToken,
            calendarId: $account->calendar_id,
            event: ['id' => $eventId] + $event,
            notifyAttendees: $account->invite_patient,
        );

        // Evento já criado em uma tentativa anterior
        return $result ?? $this->wrapper->patchEvent($accessToken, $account->calendar_id, $eventId, $event, $account->invite_patient)
            ?? throw new \UnexpectedValueException('event_conflict');
    }

    /**
     * @return array{id: string}
     */
    private function patchOrRecreate(string $accessToken, GoogleCalendarAccount $account, Agenda $agenda, string $eventId, array $event): array
    {
        $result = $this->wrapper->patchEvent($accessToken, $account->calendar_id, $eventId, $event, $account->invite_patient);

        if ($result !== null) {
            return $result;
        }

        // Evento apagado no Google (ou conta Google trocada): o id antigo não pode ser reaproveitado
        $newId = $this->newEventId($agenda);
        $this->agenda->updateGoogleEventQuietly($agenda->id, ['google_event_id' => $newId, 'google_synced_at' => null]);

        return $this->createOrPatch($accessToken, $account, $newId, $event);
    }

    /**
     * Título e descrição mínimos: nada de dados clínicos, observações ou nome completo no Google.
     */
    private function buildEvent(Agenda $agenda, GoogleCalendarAccount $account): array
    {
        $timezone = $agenda->user->timezone ?: config('app.timezone');
        $firstName = Str::before((string) $agenda->patient?->name, ' ');

        $event = [
            'summary' => $firstName !== '' ? __('google-calendar.event_title', ['name' => $firstName]) : __('google-calendar.event_title_no_name'),
            'description' => __('google-calendar.event_description'),
            'start' => ['dateTime' => $agenda->scheduled_on->copy()->setTimezone($timezone)->toRfc3339String(), 'timeZone' => $timezone],
            'end' => ['dateTime' => $agenda->ends_at->copy()->setTimezone($timezone)->toRfc3339String(), 'timeZone' => $timezone],
            'extendedProperties' => ['private' => ['psi4_agenda_id' => (string) $agenda->id]],
        ];

        // Presencial: endereço do consultório. Online: link próprio da consulta (informado manualmente ou sala do PSI4)
        $location = $agenda->type === AgendaTypeEnum::IN_PERSON->value
            ? $this->psychologistSettingService->details($agenda->user_id)?->fullAddress()
            : $agenda->room_link;

        if ($location) {
            $event['location'] = $location;
        }

        if ($account->invite_patient && $agenda->patient?->email) {
            $event['attendees'] = [['email' => $agenda->patient->email]];
        }

        return $event;
    }

    /**
     * Só consulta online sem link próprio ganha a sala do PSI4.
     */
    private function needsRoom(Agenda $agenda): bool
    {
        return $agenda->type !== AgendaTypeEnum::IN_PERSON->value && empty($agenda->room_link);
    }

    /**
     * O Google exige ids de 5 a 1024 caracteres em base32hex (a-v e 0-9).
     */
    private function newEventId(Agenda $agenda): string
    {
        return 'psi4'.dechex($agenda->id).bin2hex(random_bytes(8));
    }
}
