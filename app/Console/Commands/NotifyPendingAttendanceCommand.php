<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RegisterLog;
use App\Models\Agenda;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class NotifyPendingAttendanceCommand extends Command
{
    protected $signature = 'app:notify-pending-attendance-command';
    protected $description = 'Avisa o psicólogo das consultas encerradas sem presença informada (compareceu ou faltou)';

    // Tempo após o fim da consulta antes de cobrar o psicólogo
    private const TOLERANCE_MINUTES = 30;

    private const CHUNK = 100;

    public function handle(Agenda $agenda, NotificationService $notificationService): int
    {
        try {
            $endedBefore = now()->subMinutes(self::TOLERANCE_MINUTES);
            $lastId = 0;
            $total = 0;

            // A trava de idempotência da notificação evita avisar duas vezes a mesma consulta
            while (($ids = $agenda->pendingAttendanceIds($endedBefore, $lastId, self::CHUNK))->isNotEmpty()) {
                foreach ($ids as $id) {
                    $total += (int) $notificationService->notifyAgendaAttendancePending($id);
                }

                $lastId = (int) $ids->last();
            }

            $this->info($total.' aviso(s) de presença pendente registrado(s).');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            RegisterLog::dispatchSync([
                'message' => 'Erro ao avisar presenças pendentes: '.$e->getMessage(),
                'data' => ['trace' => $e->getTraceAsString()],
                'route' => 'app:notify-pending-attendance-command',
                'method' => 'COMMAND',
            ]);

            return self::FAILURE;
        }
    }
}
