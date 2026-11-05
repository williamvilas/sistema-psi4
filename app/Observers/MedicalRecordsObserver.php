<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\PaginateCacheContextEnum;
use App\Helpers\DashboardCache;
use App\Helpers\PaginateCache;
use App\Models\MedicalRecords;

class MedicalRecordsObserver
{
    public function created(MedicalRecords $medicalRecords): void
    {
        DashboardCache::forget($medicalRecords->user_id);
        PaginateCache::forget($medicalRecords->user_id, PaginateCacheContextEnum::MEDICAL_RECORDS);
    }

    public function updated(MedicalRecords $medicalRecords): void
    {
        DashboardCache::forget($medicalRecords->user_id);
        PaginateCache::forget($medicalRecords->user_id, PaginateCacheContextEnum::MEDICAL_RECORDS);
    }

    public function deleted(MedicalRecords $medicalRecords): void
    {
        DashboardCache::forget($medicalRecords->user_id);
        PaginateCache::forget($medicalRecords->user_id, PaginateCacheContextEnum::MEDICAL_RECORDS);
    }

    public function restored(MedicalRecords $medicalRecords): void
    {
        DashboardCache::forget($medicalRecords->user_id);
        PaginateCache::forget($medicalRecords->user_id, PaginateCacheContextEnum::MEDICAL_RECORDS);
    }

    public function forceDeleted(MedicalRecords $medicalRecords): void
    {
        DashboardCache::forget($medicalRecords->user_id);
        PaginateCache::forget($medicalRecords->user_id, PaginateCacheContextEnum::MEDICAL_RECORDS);
    }
}
