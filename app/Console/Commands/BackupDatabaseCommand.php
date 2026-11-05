<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RegisterLog;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\DbDumper\Databases\MySql;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'app:backup-database-command';
    protected $description = 'Command description';
    protected $directoryBk = 'backup-trade';

    public function handle(TelegramService $telegramService)
    {
        $this->deleteFiles(30);

        $date = date('Y_m_d');

        $connections = [
            config('database.connections.mysql'),
        ];

        $totalBkSuccess = 0;

        foreach ($connections as $connection) {
            $file = $connection['database']."_{$date}_dump.sql";

            MySql::create()
                ->setDbName($connection['database'])
                ->setUserName($connection['username'])
                ->setPassword($connection['password'])
                ->setHost($connection['host'])
                ->dumpToFile($file);

            $localFilePath = base_path($file);

            if (file_exists($localFilePath)) {
                Storage::put("{$this->directoryBk}/{$date}/{$file}", file_get_contents($localFilePath));
                unlink($file);
                $message = '✅ Backup enviado com sucesso para S3: '.$connection['database'];
                $this->info($message);
                $telegramService->sendMessage($message);
                $totalBkSuccess = $totalBkSuccess + 1;
            } else {
                $message = 'Arquivo de backup do MYSQL não encontrado: '.$file;
                $this->error($message);
                RegisterLog::dispatchSync([
                    'data' => [],
                    'message' => $message,
                    'route' => null,
                    'method' => 'BkDatabaseCommand->handle',
                ]);
            }
        }
    }

    private function deleteFiles(int $days = 0): void
    {
        $date = Carbon::now()->subDays($days)->format('Y_m_d');
        $files = Storage::files("{$this->directoryBk}/{$date}");

        if (empty($files)) {
            return;
        }

        foreach ($files as $file) {
            $timestamp = Storage::lastModified($file);
            $lastModified = Carbon::createFromTimestamp($timestamp)->setTimezone('America/Sao_Paulo');

            if ($lastModified->lt(Carbon::now()->subDays($days))) {
                Storage::delete($file);
                $this->line("Arquivo removido: {$file}");
            }
        }
    }
}
