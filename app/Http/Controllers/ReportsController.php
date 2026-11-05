<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FeatureUsageEnum;
use App\Helpers\UserHelper;
use App\Http\Requests\Reports\FilterRequest;
use App\Jobs\SaveFeatureUsageJob;
use App\Services\AgendaReportService;
use App\Services\ReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportsController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly AgendaReportService $agendaReportService,
    ) {
    }

    public function financial(): View
    {
        $today = UserHelper::getDateCurrent();

        return view('reports.financial', [
            'startDate' => $today->copy()->startOfMonth()->toDateString(),
            'endDate' => $today->toDateString(),
        ]);
    }

    public function financialData(FilterRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->trackSearch($request);

            $report = $this->reportService->financial(
                userId: Auth::id(),
                timezone: UserHelper::getTimezone(),
                startDate: $request->validated('start_date'),
                endDate: $request->validated('end_date'),
            );

            return [
                'html' => view('reports.partials.financial-summary', ['report' => $report])->render(),
                'received' => $report['received'],
            ];
        });
    }

    public function attendance(): View
    {
        $today = UserHelper::getDateCurrent();

        return view('reports.attendance', [
            'startDate' => $today->copy()->startOfMonth()->toDateString(),
            'endDate' => $today->toDateString(),
        ]);
    }

    public function attendanceData(FilterRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->trackSearch($request);

            $report = $this->agendaReportService->attendance(
                userId: Auth::id(),
                timezone: UserHelper::getTimezone(),
                startDate: $request->validated('start_date'),
                endDate: $request->validated('end_date'),
            );

            return [
                'html' => view('reports.partials.attendance-summary', ['report' => $report])->render(),
                'chart' => $report['chart'],
            ];
        });
    }

    public function attendanceExport(FilterRequest $request): BinaryFileResponse
    {
        $export = $this->agendaReportService->attendanceExport(
            userId: Auth::id(),
            timezone: UserHelper::getTimezone(),
            startDate: $request->validated('start_date'),
            endDate: $request->validated('end_date'),
        );

        return Excel::download($export, __('reports.attendance_export_filename').'-'.$request->validated('start_date').'-'.$request->validated('end_date').'.xlsx');
    }

    public function occupancy(): View
    {
        $today = UserHelper::getDateCurrent();

        return view('reports.occupancy', [
            'startDate' => $today->copy()->startOfMonth()->toDateString(),
            'endDate' => $today->copy()->endOfMonth()->toDateString(),
        ]);
    }

    public function occupancyData(FilterRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->trackSearch($request);

            $report = $this->agendaReportService->occupancy(
                userId: Auth::id(),
                timezone: UserHelper::getTimezone(),
                startDate: $request->validated('start_date'),
                endDate: $request->validated('end_date'),
            );

            return [
                'html' => view('reports.partials.occupancy-summary', ['report' => $report])->render(),
                'chart' => $report['chart'],
            ];
        });
    }

    private function trackSearch(FilterRequest $request): void
    {
        if ($request->shouldTrack()) {
            SaveFeatureUsageJob::dispatch(userId: Auth::id(), feature: FeatureUsageEnum::SEARCH_REPORT);
        }
    }
}
