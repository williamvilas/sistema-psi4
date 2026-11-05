<?php

declare(strict_types=1);

namespace App\Http\Requests\BookingSettings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'min:3',
                'max:60',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('users', 'slug')->ignore(Auth::id()),
            ],
            'public_booking_enabled' => ['nullable', 'boolean'],
            'services' => [Rule::requiredIf(fn () => $this->boolean('public_booking_enabled')), 'nullable', 'array'],
            'services.*.id' => ['nullable', 'integer'],
            'services.*.name' => ['required', 'string', 'max:100'],
            'services.*.price' => ['required', 'regex:/^\d{1,3}(\.\d{3})*(,\d{1,2})?$|^\d+(,\d{1,2})?$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Ignora linhas de serviço totalmente em branco (ex.: linha inicial não preenchida)
        $this->merge([
            'services' => collect($this->input('services', []))
                ->filter(fn ($service) => is_array($service) && (trim((string) ($service['name'] ?? '')) !== '' || trim((string) ($service['price'] ?? '')) !== ''))
                ->values()
                ->all(),
        ]);
    }

    public function attributes(): array
    {
        return [
            'slug' => 'endereço da página',
            'services' => 'serviços',
            'services.*.name' => 'nome do serviço',
            'services.*.price' => 'valor do serviço',
        ];
    }

    public function toPayload(): array
    {
        return [
            'slug' => $this->input('slug'),
            'public_booking_enabled' => $this->boolean('public_booking_enabled'),
            'services' => collect($this->input('services', []))
                ->map(fn (array $service) => [
                    'id' => $service['id'] ?? null,
                    'name' => trim($service['name']),
                    'price' => (float) str_replace(',', '.', str_replace('.', '', $service['price'])),
                ])
                ->values()
                ->all(),
        ];
    }
}
