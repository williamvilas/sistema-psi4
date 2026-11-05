<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\UserHelper;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService)
    {
    }

    public function report(): JsonResponse
    {
        return $this->response(function () {
            $report = $this->dashboardService->report(userId: Auth::id(), timezone: UserHelper::getTimezone());

            return [
                'html' => view('dashboard.reports', ['report' => $report])->render(),
                'chart' => $report['weekly_sessions'],
            ];
        });
    }
}
