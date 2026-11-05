<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RegisterLog;
use App\Models\Agenda;
use App\Models\MedicalRecords;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteAgendaCommand extends Command
{
    protected $signature = 'app:delete-agenda-command';
    protected $description = 'Command description';

    public function handle(Agenda $agenda)
    {
        try {
            $agenda->getAllDeletedCursor()
                ->chunk(10)
                ->each(function ($chunk) {

                    DB::transaction(function () use ($chunk) {

                        $ids = $chunk->pluck('id')->all();

                        MedicalRecords::withTrashed()
                            ->whereIn('agenda_id', $ids)
                            ->forceDelete();

                        Agenda::withTrashed()
                            ->whereIn('id', $ids)
                            ->forceDelete();
                    });
                });

            RegisterLog::dispatchSync([
                'message' => 'Agendas deletadas com sucesso.',
                'method' => 'DeleteAgendaCommand->handle',
            ]);

            $this->info('Agendas deletadas com sucesso.');

        } catch (\Throwable $t) {
            RegisterLog::dispatchSync([
                'message' => 'Falha em deletar agendas. Mensagem: '.$t->getMessage(),
                'method' => 'DeleteAgendaCommand->handle',
            ]);

            $this->error($t->getMessage());
        }
    }
}
