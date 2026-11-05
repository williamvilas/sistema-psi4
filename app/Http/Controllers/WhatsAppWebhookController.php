<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\WhatsAppSignatureHelper;
use App\Jobs\ProcessWhatsAppWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    /**
     * Verificação do endpoint ao cadastrar o webhook no app da Meta (devolve o `hub.challenge`).
     */
    public function verify(Request $request): Response
    {
        $token = (string) config('whatsapp.verify_token');

        if ($token === '' || $request->query('hub_mode') !== 'subscribe' || ! hash_equals($token, (string) $request->query('hub_verify_token'))) {
            return response('', 403);
        }

        return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Status das mensagens e respostas dos pacientes. A assinatura é validada antes de qualquer
     * processamento; a resposta 200 sai na hora e o processamento vai para a fila.
     */
    public function store(Request $request): JsonResponse
    {
        if (! WhatsAppSignatureHelper::isValid($request, (string) config('whatsapp.app_secret'))) {
            return response()->json(['message' => 'invalid_signature'], 401);
        }

        ProcessWhatsAppWebhookJob::dispatch(payload: $request->all());

        return response()->json([], 200);
    }
}
