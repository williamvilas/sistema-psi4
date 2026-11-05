<?php

declare(strict_types=1);

namespace App\Http\Requests\GoogleCalendar;

use Illuminate\Foundation\Http\FormRequest;

class CallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required_without:error', 'string'],
            'state' => ['required', 'string'],
            'error' => ['nullable', 'string'],
        ];
    }

    public function getCode(): ?string
    {
        return $this->filled('code') ? (string) $this->input('code') : null;
    }

    public function getState(): string
    {
        return (string) $this->input('state');
    }

    public function getError(): ?string
    {
        return $this->filled('error') ? (string) $this->input('error') : null;
    }
}
