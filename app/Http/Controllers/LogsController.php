<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Logs\StoreRequest;
use App\Services\LogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LogsController extends Controller
{
    public function __construct(private LogService $logService)
    {

    }

    public function store(StoreRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $data = [
                'user_id' => Auth::id(),
                'message' => $request->get('message'),
                'route' => $request->get('route'),
            ];

            $this->logService->store(data: $data);

            return [];
        });

    }
}
