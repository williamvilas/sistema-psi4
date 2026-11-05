<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Http\Requests\CustomRequest;

class DefaultRequest extends CustomRequest
{
    public function rules(): array
    {
        return [
            'field_key' => ['required', 'string', 'regex:/^text_[1-9]$/'],
            'value' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
