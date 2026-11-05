<?php

declare(strict_types=1);

namespace App\Http\Requests\WhatsApp;

use App\Http\Requests\CustomRequest;

class SendRequest extends CustomRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required'],
            'text' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function getPhone(): string
    {
        return $this->route('phone');
    }

    public function getText(): ?string
    {
        return $this->validated('text');
    }
}
