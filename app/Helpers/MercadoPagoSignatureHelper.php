<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Http\Request;

/**
 * Validação do header `x-signature` enviado pelo Mercado Pago em toda
 * notificação webhook (HMAC-SHA256), conforme padrão oficial:
 * https://www.mercadopago.com.br/developers -> Webhooks -> Assinatura secreta.
 */
class MercadoPagoSignatureHelper
{
    public static function isValid(Request $request, string $secret): bool
    {
        if ($secret === '') {
            return false;
        }

        $signature = (string) $request->header('x-signature', '');
        $requestId = (string) $request->header('x-request-id', '');
        $dataId = (string) $request->input('data.id', '');

        if ($signature === '' || $requestId === '' || $dataId === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signature) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, null);

            if ($key !== null && $value !== null) {
                $parts[trim($key)] = trim($value);
            }
        }

        $ts = $parts['ts'] ?? null;
        $v1 = $parts['v1'] ?? null;

        if (! $ts || ! $v1) {
            return false;
        }

        $canonical = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $expected = hash_hmac('sha256', $canonical, $secret);

        return hash_equals($expected, $v1);
    }
}
