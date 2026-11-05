<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autenticação das rotas internas de salas de vídeo por token de serviço (Bearer = PSI_VIDEO_API_TOKEN).
 */
class AuthenticateVideoRoomToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('psi_video.api_token');
        $token = (string) $request->bearerToken();

        if ($expected === '' || $token === '' || ! hash_equals($expected, $token)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
