<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AgendaSettings\StoreRequest;
use App\Services\AgendaSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AgendaSettingsController extends Controller
{
    public function __construct(
        private readonly AgendaSettingService $settingService,
    ) {
    }

    public function store(StoreRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->settingService->store(
                data: $request->toPayload(),
                userId: Auth::id(),
            );

            return $this->getMessageSuccess();
        });
    }
}
