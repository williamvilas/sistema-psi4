<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use App\Helpers\NumberHelper;
use App\Helpers\StringHelper;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'document' => ['nullable', 'cpf'],
            'whatsapp' => ['required', 'celular_com_ddd'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'document' => 'Documento inválido.',
        ];
    }

    public function payload(): array
    {
        return [
            'name' => StringHelper::firstLetterCapitalized($this->request->get('name')),
            'document' => NumberHelper::onlyNumbers((string) $this->request->get('document')),
            'whatsapp' => NumberHelper::onlyNumbers((string) $this->request->get('whatsapp')),
            'email' => $this->request->get('email'),
        ];
    }
}
