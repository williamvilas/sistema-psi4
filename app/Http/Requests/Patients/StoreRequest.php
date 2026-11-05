<?php

declare(strict_types=1);

namespace App\Http\Requests\Patients;

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
            'name' => ['required'],
            'birth_date' => ['nullable', 'date_format:d/m/Y'],
            'document' => ['nullable', 'required_unless:no_document,1', 'cpf'],
            'no_document' => ['nullable', 'boolean'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'payer_document' => ['nullable', 'cpf'],
            'phone' => ['required', 'celular_com_ddd'],
            'email' => ['nullable', 'email'],
            'whatsapp_notifications' => ['nullable', 'boolean'],
            'notes' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'document.required_unless' => __('errors.document_required'),
            'document.cpf' => __('errors.document_invalid'),
            'payer_document.cpf' => __('errors.document_invalid'),
        ];
    }

    public function toInsert(): array
    {
        return [
            'name' => $this->input('name'),
            'birth_date' => $this->input('birth_date'),
            'document' => $this->boolean('no_document') ? null : $this->input('document'),
            'payer_name' => $this->boolean('has_payer') ? $this->input('payer_name') : null,
            'payer_document' => $this->boolean('has_payer') ? $this->input('payer_document') : null,
            'phone' => $this->input('phone'),
            'email' => $this->input('email'),
            'whatsapp_notifications' => ! $this->has('whatsapp_notifications') || $this->boolean('whatsapp_notifications'),
            'notes' => $this->input('notes'),
        ];
    }
}
