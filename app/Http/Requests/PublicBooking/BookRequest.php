<?php

declare(strict_types=1);

namespace App\Http\Requests\PublicBooking;

use App\Enums\AgendaTypeEnum;
use App\Helpers\NumberHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'phone' => ['required', 'celular_com_ddd'],
            // Quem não tem CPF (ex.: estrangeiro) marca no_document e entra na sala pelo telefone
            'no_document' => ['nullable', 'boolean'],
            'document' => ['exclude_if:no_document,1', 'required', 'cpf'],
            'has_payer' => ['exclude_if:no_document,1', 'required', 'boolean'],
            'payer_name' => ['exclude_if:no_document,1', 'exclude_unless:has_payer,1', 'required', 'string', 'max:255'],
            'payer_document' => ['exclude_if:no_document,1', 'exclude_unless:has_payer,1', 'required', 'cpf', 'different:document'],
            'service_id' => ['required', 'integer'],
            'type' => ['required', Rule::enum(AgendaTypeEnum::class)],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            // Honeypot: usuários reais nunca preenchem
            'website' => ['nullable', 'max:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'document.required' => __('errors.document_required'),
            'document.cpf' => __('errors.document_invalid'),
            'has_payer.required' => __('public-booking.payer_question_required'),
            'payer_document.cpf' => __('errors.document_invalid'),
            'payer_document.different' => __('public-booking.payer_document_same'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_map(
            fn (mixed $value) => is_string($value) ? NumberHelper::onlyNumbers($value) : $value,
            $this->only(['document', 'payer_document']),
        ));
    }

    public function toBook(): array
    {
        $hasDocument = ! $this->boolean('no_document');
        $hasPayer = $hasDocument && $this->boolean('has_payer');

        return [
            'name' => $this->input('name'),
            'phone' => $this->input('phone'),
            'document' => $hasDocument ? $this->input('document') : null,
            'payer_name' => $hasPayer ? $this->input('payer_name') : null,
            'payer_document' => $hasPayer ? $this->input('payer_document') : null,
            'service_id' => $this->input('service_id'),
            'type' => $this->input('type'),
            'date' => $this->input('date'),
            'time' => $this->input('time'),
        ];
    }
}
