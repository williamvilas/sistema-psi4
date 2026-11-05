<?php

declare(strict_types=1);

namespace App\Http\Requests\EncryptionKey;

class UpdatePassphraseRequest extends WrapRequest
{
    public function rules(): array
    {
        return [
            ...$this->wrapRules('passphrase'),
            'key_check' => self::KEY_CHECK_RULES,
        ];
    }

    public function toUpdate(): array
    {
        return $this->only([...$this->wrapFields('passphrase'), 'key_check']);
    }
}
