<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\HolidayHelper;
use App\Http\Requests\AgendaSettings\StoreRequest as AgendaStoreRequest;
use App\Http\Requests\BookingSettings\CheckSlugRequest;
use App\Http\Requests\Onboarding\BookingRequest;
use App\Http\Requests\Onboarding\PsychologistRequest;
use App\Services\AgendaSettingService;
use App\Services\BookingSettingService;
use App\Services\OnboardingService;
use App\Services\PsychologistSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboardingService,
        private readonly AgendaSettingService $agendaSettingService,
        private readonly PsychologistSettingService $psychologistSettingService,
        private readonly BookingSettingService $bookingSettingService,
    ) {
    }

    public function index()
    {
        return view('onboarding.index', [
            'step' => $this->onboardingService->currentStep(userId: Auth::id()),
            'agendaSettings' => $this->agendaSettingService->details(userId: Auth::id()),
            'psychologistSettings' => $this->psychologistSettingService->details(userId: Auth::id()),
            'services' => $this->bookingSettingService->services(userId: Auth::id()),
            'suggestedSlug' => $this->bookingSettingService->suggestSlug(user: Auth::user()),
        ]);
    }

    public function storeAgenda(AgendaStoreRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->agendaSettingService->store(
                data: $request->toPayload(),
                userId: Auth::id(),
            );

            return $this->getMessageSuccess() + ['step' => OnboardingService::STEP_PSYCHOLOGIST];
        });
    }

    public function storePsychologist(PsychologistRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->onboardingService->storePsychologist(
                user: Auth::user(),
                settings: $request->toPayload(),
                contact: $request->contactPayload(),
            );

            return $this->getMessageSuccess() + ['step' => OnboardingService::STEP_BOOKING];
        });
    }

    public function storeBooking(BookingRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->bookingSettingService->store(
                data: $request->toPayload(),
                userId: Auth::id(),
            );

            $this->onboardingService->complete(user: Auth::user());

            return $this->getMessageSuccess() + ['link' => route('dashboard')];
        });
    }

    public function checkSlug(CheckSlugRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $available = $this->bookingSettingService->isSlugAvailable(
                slug: $request->validated('slug'),
                userId: Auth::id(),
            );

            return [
                'available' => $available,
                'message' => __($available ? 'public-booking.slug_available' : 'public-booking.slug_unavailable'),
            ];
        });
    }

    public function nationalHolidays(): JsonResponse
    {
        return $this->response(fn () => ['holidays' => HolidayHelper::national()]);
    }
}
