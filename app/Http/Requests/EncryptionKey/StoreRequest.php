<?php

declare(strict_types=1);

namespace App\Http\Requests\EncryptionKey;

class StoreRequest extends WrapRequest
{
    public function rules(): array
    {
        return [
            ...$this->wrapRules('passphrase'),
            ...$this->wrapRules('recovery'),
            'key_check' => self::KEY_CHECK_RULES,
        ];
    }

    public function toInsert(): array
    {
        return $this->only([
            ...$this->wrapFields('passphrase'),
            ...$this->wrapFields('recovery'),
            'key_check',
        ]);
    }
}
