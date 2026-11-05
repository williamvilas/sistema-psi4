<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Http\Request;

/**
 * Validação do header `X-Hub-Signature-256` enviado pela Meta em todo webhook do WhatsApp:
 * HMAC-SHA256 do corpo bruto da requisição com o segredo do app.
 */
class WhatsAppSignatureHelper
{
    public static function isValid(Request $request, string $secret): bool
    {
        $signature = (string) $request->header('X-Hub-Signature-256', '');

        if ($secret === '' || ! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, substr($signature, 7));
    }
}
