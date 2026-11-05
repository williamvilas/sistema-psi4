<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Helpers\FileHelper;
use App\Jobs\RegisterLog;
use App\Models\MedicalRecords;
use App\Models\SessionActivities;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteMedicalRecordsCommand extends Command
{
    protected $signature = 'app:delete-medical-records-command';
    protected $description = 'Command description';

    public function handle(MedicalRecords $medicalRecords)
    {
        try {
            $medicalRecords->chunkDeletedWithoutAgenda(100, function ($chunk) use ($medicalRecords) {
                $ids = $chunk->pluck('id')->all();
                // O registro original da dinâmica sai junto (cascade): guardar o caminho antes para apagar o arquivo.
                $activityPaths = SessionActivities::query()->whereIn('medical_record_id', $ids)->pluck('content_path');

                DB::transaction(function () use ($medicalRecords, $ids) {
                    $medicalRecords->forceDeleteTrashedByIds($ids);
                });

                // Arquivos removidos só após o commit, evitando órfãos no storage.
                foreach ($chunk->pluck('notes_path')->filter()->merge($activityPaths) as $path) {
                    FileHelper::remove($path);
                }
            });

            RegisterLog::dispatchSync([
                'message' => 'Prontuários deletados com sucesso.',
                'method' => 'DeleteMedicalRecordsCommand->handle',
            ]);

            $this->info('Prontuários deletados com sucesso.');

        } catch (\Throwable $t) {
            RegisterLog::dispatchSync([
                'message' => 'Falha em deletar Prontuários. Mensagem: '.$t->getMessage(),
                'method' => 'DeleteMedicalRecordsCommand->handle',
            ]);

            $this->error($t->getMessage());
        }
    }
}
