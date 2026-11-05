<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Dashboard\StoreScreenResolutionRequest;
use App\Services\UserScreenResolutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ScreenController extends Controller
{
    public function store(StoreScreenResolutionRequest $request, UserScreenResolutionService $service): JsonResponse
    {
        return $this->response(function () use ($request, $service) {
            $service->store(
                data: $request->validated(),
                userId: Auth::id(),
            );

            return $this->getMessageSuccess();
        });
    }
}
