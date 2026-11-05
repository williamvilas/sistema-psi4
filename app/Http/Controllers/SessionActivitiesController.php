<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SessionActivities\ThermometerRequest;
use App\Http\Requests\SessionActivities\UpdatePinRequest;
use App\Services\SessionActivityService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SessionActivitiesController extends Controller
{
    public function __construct(private readonly SessionActivityService $sessionActivityService)
    {
    }

    public function index(): View
    {
        return view('session-activities.index', [
            'status' => $this->sessionActivityService->activationStatus(Auth::user()),
            'activities' => config('session_activities.activities'),
            'disclaimer' => config('session_activities.disclaimer'),
        ]);
    }

    public function updatePin(UpdatePinRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->sessionActivityService->updatePin(Auth::user(), (string) $request->validated('pin'));

            return ['message' => 'PIN definido.'];
        });
    }

    public function thermometer(ThermometerRequest $request): JsonResponse
    {
        return $this->response(fn () => [
            'activities' => $this->sessionActivityService->thermometerByPatient(patientId: $request->getPatientId(), userId: Auth::id()),
        ]);
    }
}
