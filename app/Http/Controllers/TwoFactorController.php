<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TwoFactor\ConfirmRequest;
use App\Http\Requests\TwoFactor\DisableRequest;
use App\Http\Requests\TwoFactor\RecoveryCodesRequest;
use App\Services\TwoFactorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TwoFactorController extends Controller
{
    public function __construct(private TwoFactorService $service)
    {
    }

    public function index(): View
    {
        return view('two-factor.index', [
            'twoFactor' => $this->service->getStatus(Auth::user()),
        ]);
    }

    public function setup(): JsonResponse
    {
        return $this->response(fn () => $this->service->setup(Auth::user()));
    }

    public function confirm(ConfirmRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $recoveryCodes = $this->service->confirm(Auth::user(), $request->getCode());

            return ['recovery_codes' => $recoveryCodes];
        });
    }

    public function destroy(DisableRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->service->disable(Auth::user(), $request->getCode());

            return ['status' => 'disabled'];
        });
    }

    public function recoveryCodes(RecoveryCodesRequest $request): JsonResponse
    {
        return $this->response(fn () => [
            'recovery_codes' => $this->service->regenerateRecoveryCodes(
                Auth::user(),
                $request->getCode()
            ),
        ]);
    }
}
