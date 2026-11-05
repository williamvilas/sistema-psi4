<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RegisterLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DeleteFilesTemporaryCommand extends Command
{
    protected $signature = 'app:delete-files-temporary-command {directory} {--hours=4} {--dry-run}';
    protected $description = 'Delete S3 files older than X hours in a directory safely';

    public function handle()
    {
        $directory = rtrim($this->argument('directory'), '/').'/';
        $hours = (int) $this->option('hours');
        $dryRun = $this->option('dry-run');

        $disk = Storage::disk('s3');
        $client = $disk->getClient();
        $bucket = config('filesystems.disks.s3.bucket');

        $params = [
            'Bucket' => $bucket,
            'Prefix' => $directory,
            'MaxKeys' => 500, // reduz uso de memoria
        ];

        $now = Carbon::now();
        $totalDeleted = 0;
        $totalScanned = 0;

        $this->info("Iniciando limpeza em: {$directory}");
        $this->info("Arquivos com mais de {$hours} horas serão removidos");
        if ($dryRun) {
            $this->warn('Modo DRY-RUN ativado (não deleta)');
        }

        do {
            $result = $client->listObjectsV2($params);

            if (empty($result['Contents'])) {
                break;
            }

            $objectsToDelete = [];

            foreach ($result['Contents'] as $object) {
                $totalScanned++;

                $lastModified = Carbon::parse($object['LastModified']);

                if ($lastModified->diffInHours($now) >= $hours) {
                    $objectsToDelete[] = [
                        'Key' => $object['Key'],
                    ];
                }

                // Evita array crescer demais
                if (count($objectsToDelete) === 500) {
                    $this->deleteChunk($client, $bucket, $objectsToDelete, $dryRun);
                    $totalDeleted += count($objectsToDelete);
                    $objectsToDelete = [];

                    // Libera memoria imediatamente
                    gc_collect_cycles();
                }
            }

            // Deleta restante
            if (! empty($objectsToDelete)) {
                $this->deleteChunk($client, $bucket, $objectsToDelete, $dryRun);
                $totalDeleted += count($objectsToDelete);
            }

            // Log de progresso
            $this->line("Scanned: {$totalScanned} | Deleted: {$totalDeleted}");

            // Paginacao segura
            $params['ContinuationToken'] = $result['NextContinuationToken'] ?? null;

            // Libera memoria da pagina anterior
            unset($result, $objectsToDelete);
            gc_collect_cycles();

        } while (! empty($params['ContinuationToken']));

        $this->info('Finalizado!');
        $this->info("Total analisado: {$totalScanned}");
        $this->info("Total deletado: {$totalDeleted}");

        $m = "Total analisado: {$totalScanned}\n";
        $m .= "Total deletado: {$totalDeleted}";
        $this->sendLog($m);
    }

    private function deleteChunk($client, $bucket, array $objects, $dryRun = false): void
    {
        if ($dryRun) {
            return;
        }

        $client->deleteObjects([
            'Bucket' => $bucket,
            'Delete' => [
                'Objects' => $objects,
                'Quiet' => true,
            ],
        ]);
    }

    private function sendLog(string $message): void
    {
        RegisterLog::dispatchSync([
            'message' => $message,
            'method' => 'DeleteFilesTemporaryCommand->handle',
        ]);
    }
}
