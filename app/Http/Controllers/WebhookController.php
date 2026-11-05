<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\MercadoPagoSignatureHelper;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    /**
     * Recebe as notificações webhook do Mercado Pago (assinatura do plano
     * SaaS da PSI4 e cobranças de pacientes via Mercado Pago Connect).
     *
     * A assinatura `x-signature` é validada ANTES de qualquer processamento;
     * a resposta 200 é devolvida imediatamente e o processamento acontece de
     * forma assíncrona (fila), conforme padrão obrigatório de webhooks do
     * Mercado Pago.
     */
    public function store(Request $request, WebhookService $webhookService): JsonResponse
    {
        $secret = (string) config('mercadopago.webhook_key');

        if (! MercadoPagoSignatureHelper::isValid($request, $secret)) {
            return response()->json(['message' => 'invalid_signature'], 401);
        }

        $webhookService->store(payload: $request->all());

        return response()->json([], 200);
    }
}
