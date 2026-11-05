<?php

declare(strict_types=1);

namespace App\Http\Requests\TwoFactor;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'digits:6'],
        ];
    }

    public function getCode(): string
    {
        return (string) $this->input('code');
    }
}
