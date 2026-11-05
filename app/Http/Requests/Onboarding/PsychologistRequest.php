<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\Helpers\NumberHelper;
use App\Http\Requests\PsychologistSettings\StoreRequest;

class PsychologistRequest extends StoreRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'nome_clinica' => ['required', 'string', 'max:255'],
            'crp' => ['required', 'string', 'min:9', 'max:30'],
            // CPF já informado fica travado (ProfileService), então só é exigido de quem ainda não tem
            'document' => [empty($this->user()->document) ? 'required' : 'nullable', 'cpf'],
            'whatsapp' => ['required', 'celular_com_ddd'],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'cidade.required' => __('agenda.address_required_onboarding'),
            'rua.required' => __('agenda.address_required_onboarding'),
            'numero.required' => __('agenda.address_required_onboarding'),
            'document.cpf' => 'CPF inválido.',
        ]);
    }

    public function contactPayload(): array
    {
        return [
            'document' => NumberHelper::onlyNumbers((string) $this->input('document')),
            'whatsapp' => NumberHelper::onlyNumbers((string) $this->input('whatsapp')),
        ];
    }
}
