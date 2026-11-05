<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\RegistrationInvitationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Com REGISTRATION_INVITE_ONLY=true, só quem chega com convite válido vê/envia o cadastro.
 */
class EnsureRegistrationAllowed
{
    public function __construct(private readonly RegistrationInvitationService $invitations)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->invitations->isInviteOnly()) {
            return $next($request);
        }

        $token = $request->query('convite');

        // Link do convite: guarda na sessão e limpa a URL (o token não fica no histórico nem no Referer).
        if ($request->isMethod('GET') && is_string($token) && $token !== '') {
            $this->invitations->rememberFromToken($token);

            return redirect()->route('register');
        }

        if ($this->invitations->hasValidInSession()) {
            return $next($request);
        }

        if ($request->isMethod('GET')) {
            return redirect()->route('invite.request');
        }

        return response()->json([
            'message' => __('auth.invite_required'),
            'link' => route('invite.request'),
        ], 403);
    }
}
