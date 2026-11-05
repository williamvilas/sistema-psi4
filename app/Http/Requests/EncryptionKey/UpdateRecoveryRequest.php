<?php

declare(strict_types=1);

namespace App\Http\Requests\EncryptionKey;

class UpdateRecoveryRequest extends WrapRequest
{
    public function rules(): array
    {
        return [
            ...$this->wrapRules('recovery'),
            'key_check' => self::KEY_CHECK_RULES,
        ];
    }

    public function toUpdate(): array
    {
        return $this->only([...$this->wrapFields('recovery'), 'key_check']);
    }
}
