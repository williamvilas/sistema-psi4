<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingCompleted
{
    /**
     * Rotas liberadas durante o onboarding (o wizard usa CEP/CNPJ e upload de arquivos).
     */
    private const ALLOWED_ROUTES = [
        'onboarding.*',
        'zipcode.show',
        'cnpj.show',
        'files.temporaryUrl',
        'profile.force-change-password',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || $user->hasCompletedOnboarding() || $request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => __('onboarding.pending'), 'redirect' => route('onboarding.index')], 403);
        }

        return redirect()->route('onboarding.index');
    }
}
