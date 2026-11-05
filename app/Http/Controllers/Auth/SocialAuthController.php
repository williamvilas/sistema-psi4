<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Helpers\UserHelper;
use App\Http\Controllers\Controller;
use App\Jobs\RegisterLog;
use App\Services\SocialAuthService;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    private const CANCELLED_ERRORS = ['access_denied', 'user_cancelled_login', 'user_cancelled_authorize'];

    public function redirect(string $provider, SocialAuthService $service): RedirectResponse
    {
        return Socialite::driver($service->driver($provider))->redirect();
    }

    public function callback(
        string $provider,
        Request $request,
        SocialAuthService $service,
        TwoFactorService $twoFactorService,
    ): RedirectResponse {
        $label = $service->label($provider);

        if ($request->filled('error')) {
            return $this->handleProviderError($request, $label);
        }

        try {
            $socialUser = Socialite::driver($service->driver($provider))->user();
            $user = $service->resolveUser($provider, $socialUser);
        } catch (\DomainException $d) {
            return $this->backToLogin($d->getMessage());
        } catch (\Throwable $t) {
            RegisterLog::dispatchSync([
                'message' => $t->getMessage(),
                'route' => $request->fullUrl(),
                'method' => $request->method(),
            ]);

            return $this->backToLogin(__('auth.social_failed', ['provider' => $label]));
        }

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->regenerate();
            $twoFactorService->startChallenge($user, true);

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect(UserHelper::resolveLoginRedirect());
    }

    private function handleProviderError(Request $request, string $label): RedirectResponse
    {
        $error = (string) $request->query('error');

        if (in_array($error, self::CANCELLED_ERRORS, true)) {
            return $this->backToLogin(__('auth.social_cancelled', ['provider' => $label]));
        }

        RegisterLog::dispatchSync([
            'message' => 'Erro retornado pelo provedor '.$label.': '.$error,
            'data' => ['error_description' => (string) $request->query('error_description')],
            'route' => $request->fullUrl(),
            'method' => $request->method(),
        ]);

        return $this->backToLogin(__('auth.social_failed', ['provider' => $label]));
    }

    private function backToLogin(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('social_error', $message);
    }
}
