<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RegisterLog;
use App\Models\Logs;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class SendLogsCommand extends Command
{
    protected $signature = 'app:send-logs-command';
    protected $description = 'Command description';

    public function handle(Logs $logs, TelegramService $telegramService)
    {
        try {
            $sent = $logs->getAllNotSentCursor();

            foreach ($sent as $v) {
                $m = "Usuário: {$v->user_id}\n";
                $m .= "Rota: {$v->route}\n";
                $m .= "Método: {$v->method}\n";
                $m .= "Mensagem: {$v->message}\n";

                $telegramService->sendMessage($m);

                $v->sent = true;
                $v->save();
            }

            $this->info('Logs enviados com sucesso.');
            $logs->deleteOlderThan15Days();

        } catch (\Throwable $t) {
            RegisterLog::dispatchSync([
                'message' => 'Falha em enviar logs. Mensagem: '.$t->getMessage(),
                'method' => 'SendLogsCommand->handle',
            ]);

            $this->error($t->getMessage());
        }
    }
}
