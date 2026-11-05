<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\BookingSettings\CheckSlugRequest;
use App\Http\Requests\BookingSettings\StoreRequest;
use App\Http\Requests\BookingSettings\ToggleRequest;
use App\Services\BookingSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class BookingSettingsController extends Controller
{
    public function __construct(
        private readonly BookingSettingService $settingService,
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

    public function checkSlug(CheckSlugRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $available = $this->settingService->isSlugAvailable(
                slug: $request->validated('slug'),
                userId: Auth::id(),
            );

            return [
                'available' => $available,
                'message' => __($available ? 'public-booking.slug_available' : 'public-booking.slug_unavailable'),
            ];
        });
    }

    public function toggle(ToggleRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $enabled = $this->settingService->toggle(
                enabled: $request->boolean('public_booking_enabled'),
                userId: Auth::id(),
            );

            return $this->getMessageSuccess(
                message: __($enabled ? 'public-booking.enabled' : 'public-booking.disabled'),
            ) + ['public_booking_enabled' => $enabled];
        });
    }
}
