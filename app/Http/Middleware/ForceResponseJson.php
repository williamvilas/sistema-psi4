<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceResponseJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');
        $response = $next($request);

        if (! $response->headers->get('Content-Type')) {
            $response->header('Content-Type', 'application/json');
        }

        return $response;
    }
}
