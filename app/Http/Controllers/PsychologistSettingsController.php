<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\UserHelper;
use App\Http\Requests\PsychologistSettings\StoreRequest;
use App\Services\PsychologistSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PsychologistSettingsController extends Controller
{
    public function __construct(
        private readonly PsychologistSettingService $settingService,
    ) {
    }

    public function store(StoreRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->settingService->store(
                data: $request->toPayload(),
                userId: Auth::id(),
            );

            return $this->getMessageSuccess() + [
                'photo' => UserHelper::getPhoto(Auth::user()->refresh()),
            ];
        });
    }
}
