<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\PaginateCacheContextEnum;
use App\Helpers\DashboardCache;
use App\Helpers\PaginateCache;
use App\Models\Patients;
use App\Services\AgendaVideoRoomService;

class PatientsObserver
{
    public function __construct(private readonly AgendaVideoRoomService $agendaVideoRoomService)
    {
    }

    public function created(Patients $patients): void
    {
        PaginateCache::forget($patients->user_id, PaginateCacheContextEnum::PATIENTS);
        DashboardCache::forget($patients->user_id);
    }

    public function updated(Patients $patients): void
    {
        PaginateCache::forgetPatientDependents($patients->user_id);
        DashboardCache::forget($patients->user_id);

        // Quem entra nas salas das próximas consultas online é identificado pelo CPF ou, sem ele, pelo telefone
        if ($patients->wasChanged(['document', 'phone', 'name'])) {
            $this->agendaVideoRoomService->refreshParticipantsBy('patient_id', $patients->id);
        }
    }

    public function deleted(Patients $patients): void
    {
        PaginateCache::forgetPatientDependents($patients->user_id);
        DashboardCache::forget($patients->user_id);
    }

    public function restored(Patients $patients): void
    {
        PaginateCache::forgetPatientDependents($patients->user_id);
        DashboardCache::forget($patients->user_id);
    }

    public function forceDeleted(Patients $patients): void
    {
        PaginateCache::forgetPatientDependents($patients->user_id);
        DashboardCache::forget($patients->user_id);
    }
}
