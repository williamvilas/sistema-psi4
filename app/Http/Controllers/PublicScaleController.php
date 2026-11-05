<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ScaleLinkUnavailableException;
use App\Services\ScaleLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Página pública para o paciente responder a escala, sem login.
 * Não exibe dado pessoal (nome, CPF, CRP): só a escala. As respostas são cifradas no navegador.
 */
class PublicScaleController extends Controller
{
    // Sem indexação, sem enviar a URL (com o token) para outros sites e sem cache.
    private const PRIVATE_HEADERS = [
        'X-Robots-Tag' => 'noindex, nofollow',
        'Referrer-Policy' => 'no-referrer',
        'Cache-Control' => 'no-store, private',
    ];

    public function __construct(private readonly ScaleLinkService $scaleLinkService)
    {
    }

    public function show(string $token): Response
    {
        try {
            $data = $this->scaleLinkService->findPublic($token);
        } catch (ScaleLinkUnavailableException) {
            return response()->view('public-scale.unavailable', [], 404)->withHeaders(self::PRIVATE_HEADERS);
        }

        return response()->view('public-scale.index', [
            'token' => $token,
            'scale' => $data['scale'],
            'publicKey' => $data['public_key'],
        ])->withHeaders(self::PRIVATE_HEADERS);
    }

    public function uploadUrl(string $token): JsonResponse
    {
        return $this->publicResponse(fn () => $this->scaleLinkService->issueUploadUrl($token));
    }

    public function submit(string $token): JsonResponse
    {
        return $this->publicResponse(function () use ($token) {
            $this->scaleLinkService->complete($token);

            return ['done' => true];
        });
    }

    // 410: o link encerrou (respondido, expirado ou cancelado) e não adianta tentar de novo.
    // As demais falhas seguem o tratamento padrão de Controller::response.
    private function publicResponse(callable $callback): JsonResponse
    {
        $unavailable = null;

        $response = $this->response(function () use ($callback, &$unavailable) {
            try {
                return $callback();
            } catch (ScaleLinkUnavailableException $e) {
                $unavailable = $e;

                return [];
            }
        });

        if ($unavailable) {
            $response = response()->json(['message' => $unavailable->getMessage()], 410);
        }

        return $response->withHeaders(self::PRIVATE_HEADERS);
    }
}
