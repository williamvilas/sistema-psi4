<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ForceChangePassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! empty($user->force_change_password) && ! $request->ajax()) {
            return redirect()->route('profile.force-change-password');
        }

        return $next($request);
    }
}
