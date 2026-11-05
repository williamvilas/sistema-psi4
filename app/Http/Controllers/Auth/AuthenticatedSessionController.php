<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Helpers\UserHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\TwoFactorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): View
    {
        if ($request->query('code') === 'session-expired') {
            $redirect = UserHelper::sanitizeRedirect($request->query('redirect'));

            if ($redirect) {
                session(['redirect' => $redirect]);
            }
        }

        return view('auth.login');
    }

    public function store(LoginRequest $request, TwoFactorService $twoFactorService): JsonResponse
    {
        return $this->response(function () use ($request, $twoFactorService) {
            $user = $request->validateCredentials();

            if ($user->hasTwoFactorEnabled()) {
                $request->session()->regenerate();
                $twoFactorService->startChallenge($user, $request->boolean('remember'));

                return [
                    'two_factor' => true,
                    'link' => route('two-factor.challenge', absolute: false),
                ];
            }

            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            return ['link' => UserHelper::resolveLoginRedirect()];
        });
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
