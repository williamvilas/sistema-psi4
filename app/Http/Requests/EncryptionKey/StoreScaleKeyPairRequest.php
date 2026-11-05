<?php

declare(strict_types=1);

namespace App\Http\Requests\EncryptionKey;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Par de chaves das escalas (ECDH P-256), gerado no navegador.
 * Pública: base64 do SPKI (91 bytes = 124 caracteres).
 * Privada: base64 de iv (12 bytes) + PKCS#8 cifrado com a chave dos prontuários (AES-GCM).
 */
class StoreScaleKeyPairRequest extends FormRequest
{
    private const BASE64 = 'regex:/^[A-Za-z0-9+\/]+={0,2}$/';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'public_key' => ['required', 'string', 'size:124', self::BASE64],
            'wrapped_private_key' => ['required', 'string', 'min:100', 'max:512', self::BASE64],
        ];
    }

    public function getPublicKey(): string
    {
        return (string) $this->input('public_key');
    }

    public function getWrappedPrivateKey(): string
    {
        return (string) $this->input('wrapped_private_key');
    }
}
