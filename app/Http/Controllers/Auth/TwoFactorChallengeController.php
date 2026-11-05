<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Helpers\UserHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\TwoFactor\ChallengeRequest;
use App\Services\TwoFactorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TwoFactorChallengeController extends Controller
{
    public function __construct(private TwoFactorService $service)
    {
    }

    public function create(): View|RedirectResponse
    {
        if (! $this->service->getPendingChallenge()) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(ChallengeRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $challenge = $this->service->getPendingChallenge();
            $user = $this->service->finishChallenge($request->getCode());

            session()->forget(TwoFactorService::CHALLENGE_SESSION_KEY);
            Auth::login($user, (bool) ($challenge['remember'] ?? false));
            $request->session()->regenerate();

            return ['link' => UserHelper::resolveLoginRedirect()];
        });
    }

    public function sendResetLink(): JsonResponse
    {
        return $this->response(function () {
            $this->service->sendResetLink();

            return ['title' => __('messages.title_success'), 'message' => __('two-factor.reset_link_sent')];
        });
    }

    public function showReset(Request $request, int $user, string $token): View|RedirectResponse
    {
        if (! $this->service->isResetLinkValid($user, $token)) {
            return redirect()->route('login', ['code' => 'two-factor-reset-invalid']);
        }

        return view('auth.two-factor-reset', ['action' => $request->fullUrl()]);
    }

    public function confirmReset(int $user, string $token): JsonResponse
    {
        return $this->response(function () use ($user, $token) {
            $this->service->resetByLink($user, $token);

            return ['link' => route('login', absolute: false)];
        });
    }
}
