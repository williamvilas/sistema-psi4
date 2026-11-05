<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\GoogleCalendarSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncAgendaToGoogleJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public int $agendaId)
    {
        // Só processa após o commit, evitando sincronizar agendamentos revertidos por rollback
        $this->afterCommit();
    }

    public function handle(GoogleCalendarSyncService $syncService): void
    {
        $syncService->syncAgenda($this->agendaId);
    }

    /**
     * Tentativas esgotadas: a consulta continua válida e, sem o Meet, recebe uma sala do PSI4.
     */
    public function failed(\Throwable $exception): void
    {
        app(GoogleCalendarSyncService::class)->fallbackToVideoRoom($this->agendaId);
    }
}
