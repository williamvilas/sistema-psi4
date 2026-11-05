<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AccessLogActionEnum;
use App\Http\Requests\AccessLogs\PaginateRequest;
use App\Services\AccessLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccessLogsController extends Controller
{
    public function __construct(private readonly AccessLogService $service)
    {
    }

    public function index(Request $request): View
    {
        return view('access-logs.index', [
            'actions' => AccessLogActionEnum::cases(),
            'medicalRecordId' => $request->query('medical_record_id'),
        ]);
    }

    public function paginate(PaginateRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $params = $request->getParams();
            $logs = $this->service->paginate(userId: Auth::id(), limit: config('app.paginate'), params: $params);

            if ($request->isPanel()) {
                return [
                    'html' => view('access-logs.panel-paginate', [
                        'logs' => $logs->withQueryString(),
                        'hasFilters' => $request->hasFilters(),
                    ])->render(),
                ];
            }

            return [
                'html' => view('access-logs.paginate', [
                    'logs' => $logs->withQueryString(),
                    'hasFilters' => ! empty($params),
                ])->render(),
            ];
        });
    }
}
