<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RegisterLog;
use App\Models\Agenda;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendRoomLinksCommand extends Command
{
    protected $signature = 'app:send-room-links-command';
    protected $description = 'Envia por WhatsApp o link da sala ao paciente e à psicóloga pouco antes da consulta online';

    private const CHUNK = 100;

    public function handle(Agenda $agenda, NotificationService $notificationService): int
    {
        try {
            $now = now();
            $until = $now->copy()->addMinutes((int) config('whatsapp.room_link_minutes'));
            $lastId = 0;
            $total = 0;

            // Consulta ainda sem link (Meet não gerado) volta na próxima execução até começar
            while (($ids = $agenda->startingOnlineIdsWithoutRoomLinkNotification($now, $until, $lastId, self::CHUNK))->isNotEmpty()) {
                foreach ($ids as $id) {
                    $total += $notificationService->notifyRoomLink($id);
                }

                $lastId = (int) $ids->last();
            }

            $this->info($total.' aviso(s) de link da sala registrado(s).');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            RegisterLog::dispatchSync([
                'message' => 'Erro ao enviar links das salas: '.$e->getMessage(),
                'data' => ['trace' => $e->getTraceAsString()],
                'route' => 'app:send-room-links-command',
                'method' => 'COMMAND',
            ]);

            return self::FAILURE;
        }
    }
}
