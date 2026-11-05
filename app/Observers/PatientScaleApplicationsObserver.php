<?php

declare(strict_types=1);

namespace App\Observers;

use App\Helpers\DashboardCache;
use App\Models\PatientScaleApplications;

// O alerta "Reaplicar escala" do dashboard depende das aplicações.
class PatientScaleApplicationsObserver
{
    public function created(PatientScaleApplications $application): void
    {
        DashboardCache::forget($application->user_id);
    }

    public function deleted(PatientScaleApplications $application): void
    {
        DashboardCache::forget($application->user_id);
    }

    public function restored(PatientScaleApplications $application): void
    {
        DashboardCache::forget($application->user_id);
    }

    public function forceDeleted(PatientScaleApplications $application): void
    {
        DashboardCache::forget($application->user_id);
    }
}
