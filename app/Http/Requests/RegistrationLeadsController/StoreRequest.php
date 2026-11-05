<?php

declare(strict_types=1);

namespace App\Http\Requests\RegistrationLeadsController;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'whatsapp' => ['required', 'celular_com_ddd'],
            'consent' => ['accepted'],
            // Honeypot: humanas não veem nem preenchem este campo.
            'website' => ['nullable', 'string'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'consent.accepted' => __('invitations.consent_required'),
        ];
    }
}
