<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RegisterLog;
use App\Models\Agenda;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendAgendaRemindersCommand extends Command
{
    protected $signature = 'app:send-agenda-reminders-command';
    protected $description = 'Envia por WhatsApp o lembrete das consultas com a antecedência configurada pela psicóloga';

    private const CHUNK = 100;

    public function handle(Agenda $agenda, NotificationService $notificationService): int
    {
        try {
            $now = now();
            $lastId = 0;
            $total = 0;

            // A trava de idempotência da notificação evita lembrar duas vezes a mesma consulta
            while (($ids = $agenda->dueReminderIds($now, $lastId, self::CHUNK))->isNotEmpty()) {
                foreach ($ids as $id) {
                    $total += (int) $notificationService->notifyAgendaReminder($id);
                    $agenda->updateReminderAtQuietly($id, null);
                }

                $lastId = (int) $ids->last();
            }

            $this->info($total.' lembrete(s) registrado(s).');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            RegisterLog::dispatchSync([
                'message' => 'Erro ao enviar lembretes da agenda: '.$e->getMessage(),
                'data' => ['trace' => $e->getTraceAsString()],
                'route' => 'app:send-agenda-reminders-command',
                'method' => 'COMMAND',
            ]);

            return self::FAILURE;
        }
    }
}
