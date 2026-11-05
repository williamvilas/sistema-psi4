<?php

declare(strict_types=1);

namespace App\Http\Requests\PsychologistSettings;

use App\Services\AgendaSettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Quem atende presencialmente precisa informar onde fica o consultório
        $address = $this->attendsInPerson() ? 'required' : 'nullable';

        return [
            'nome_clinica' => ['nullable', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'crp' => ['nullable', 'string', 'max:30'],
            'cep' => ['nullable', 'string', 'max:9'],
            'cidade' => [$address, 'string', 'max:255'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'rua' => [$address, 'string', 'max:255'],
            'numero' => [$address, 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'foto_psicologo_cortada' => ['nullable', 'string', 'max:7000000'],
            'remover_foto' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'cidade.required' => __('agenda.address_required'),
            'rua.required' => __('agenda.address_required'),
            'numero.required' => __('agenda.address_required'),
        ];
    }

    private function attendsInPerson(): bool
    {
        return (bool) app(AgendaSettingService::class)->details(Auth::id())?->attendance_mode?->hasInPerson();
    }

    public function toPayload(): array
    {
        return [
            'clinic_name' => $this->input('nome_clinica'),
            'cnpj' => $this->input('cnpj'),
            'crp' => $this->input('crp'),
            'cep' => $this->input('cep'),
            'cidade' => $this->input('cidade'),
            'bairro' => $this->input('bairro'),
            'rua' => $this->input('rua'),
            'numero' => $this->input('numero'),
            'complemento' => $this->input('complemento'),
            'photo_data' => $this->input('foto_psicologo_cortada'),
            'remove_photo' => $this->boolean('remover_foto'),
        ];
    }
}
