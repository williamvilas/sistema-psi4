<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Helpers\FileHelper;
use App\Jobs\RegisterLog;
use App\Models\Agenda;
use App\Models\MedicalRecords;
use App\Models\Patients;
use App\Models\PatientScaleLinks;
use App\Models\SessionActivities;
use App\Models\SessionTranscriptions;
use App\Services\SessionTranscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeletePatientsCommand extends Command
{
    protected $signature = 'app:delete-patients-command';
    protected $description = 'Command description';

    public function handle(Patients $patients, SessionTranscriptionService $sessionTranscriptionService)
    {
        try {
            $patients->getAllDeletedLazy()
                ->chunk(500)
                ->each(function ($chunk) use ($sessionTranscriptionService) {
                    // O cascade apaga as linhas das dinâmicas; os arquivos cifrados saem só depois do commit.
                    $activities = SessionActivities::query()->whereIn('patient_id', $chunk->pluck('id')->all())->get(['uuid', 'content_path']);
                    $transcriptions = (new SessionTranscriptions)->listByPatients($chunk->pluck('id')->all());

                    DB::transaction(function () use ($chunk) {

                        $ids = $chunk->pluck('id')->all();

                        MedicalRecords::withTrashed()
                            ->whereIn('patient_id', $ids)
                            ->forceDelete();

                        Agenda::withTrashed()
                            ->whereIn('patient_id', $ids)
                            ->forceDelete();

                        PatientScaleLinks::withTrashed()
                            ->whereIn('patient_id', $ids)
                            ->forceDelete();

                        Patients::withTrashed()
                            ->whereIn('id', $ids)
                            ->forceDelete();
                    });

                    foreach ($activities as $activity) {
                        FileHelper::remove($activity->content_path);
                        FileHelper::remove('medical-records/sa-'.$activity->uuid.'.psa');
                    }

                    foreach ($transcriptions as $transcription) {
                        $sessionTranscriptionService->removeDraft($transcription->uuid);
                        $sessionTranscriptionService->removeTemporary($transcription);
                    }
                });

            RegisterLog::dispatchSync([
                'message' => 'Pacientes deletado com sucesso.',
                'method' => 'DeletePatientsCommand->handle',
            ]);

            $this->info('Paciente deletado com sucesso.');

            return Command::SUCCESS;

        } catch (\Throwable $t) {
            RegisterLog::dispatchSync([
                'message' => 'Falha em deletar pacientes. Mensagem: '.$t->getMessage(),
                'method' => 'DeletePatientsCommand->handle',
            ]);

            $this->error($t->getMessage());

            return Command::FAILURE;
        }
    }
}
