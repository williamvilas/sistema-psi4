<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PaginateCacheContextEnum;
use App\Helpers\DashboardCache;
use App\Helpers\PaginateCache;
use App\Models\Agenda;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CancelOpenAgendaJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $userId, public array $patientIDs)
    {
    }

    public function handle(Agenda $agenda): void
    {
        $total = $agenda->deleteOpenByPatients(userId: $this->userId, patientIDs: $this->patientIDs);

        // Delete em massa não dispara o observer, então o cache é limpo aqui
        DashboardCache::forget($this->userId);
        PaginateCache::forget($this->userId, PaginateCacheContextEnum::AGENDA);

        $info = [
            'userId' => $this->userId,
            'patient_ids' => $this->patientIDs,
        ];

        Log::info($total.' registro(s) de agenda deletado.', $info);
    }
}
