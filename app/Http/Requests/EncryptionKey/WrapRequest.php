<?php

declare(strict_types=1);

namespace App\Http\Requests\EncryptionKey;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Regras comuns dos embrulhos da chave dos prontuários (gerados no navegador).
 * Embrulho: base64 de iv (12 bytes) + chave AES-256 cifrada com AES-GCM (32 + 16 bytes de tag) = 80 caracteres.
 * Sal: base64 de 16 bytes = 24 caracteres.
 */
abstract class WrapRequest extends FormRequest
{
    public const MIN_ITERATIONS = 600000;
    public const MAX_ITERATIONS = 5000000;

    protected const KEY_CHECK_RULES = ['required', 'string', 'regex:/^[a-f0-9]{64}$/'];

    public function authorize(): bool
    {
        return true;
    }

    protected function wrapRules(string $prefix): array
    {
        return [
            $prefix.'_wrapped_key' => ['required', 'string', 'size:80', 'regex:/^[A-Za-z0-9+\/]+={0,2}$/'],
            $prefix.'_salt' => ['required', 'string', 'size:24', 'regex:/^[A-Za-z0-9+\/]+={0,2}$/'],
            $prefix.'_iterations' => ['required', 'integer', 'min:'.self::MIN_ITERATIONS, 'max:'.self::MAX_ITERATIONS],
        ];
    }

    protected function wrapFields(string $prefix): array
    {
        return [$prefix.'_wrapped_key', $prefix.'_salt', $prefix.'_iterations'];
    }
}
