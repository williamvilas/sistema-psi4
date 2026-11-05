<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfOnboardingCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::user()?->hasCompletedOnboarding()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => __('onboarding.already_completed'), 'redirect' => route('dashboard')], 403);
            }

            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
