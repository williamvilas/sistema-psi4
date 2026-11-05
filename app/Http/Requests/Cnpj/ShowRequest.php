<?php

declare(strict_types=1);

namespace App\Http\Requests\Cnpj;

use Canducci\OpenCnpj\Utils\Valid;
use Illuminate\Foundation\Http\FormRequest;

class ShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cnpj' => preg_replace('/\D/', '', (string) $this->route('cnpj')),
        ]);
    }

    public function rules(): array
    {
        return [
            'cnpj' => [
                'required',
                'digits:14',
                fn (string $attribute, mixed $value, \Closure $fail) => Valid::isCnpj((string) $value) || $fail(__('cnpj.invalid')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'cnpj.required' => __('cnpj.invalid'),
            'cnpj.digits' => __('cnpj.invalid'),
        ];
    }

    public function getCnpj(): string
    {
        return (string) $this->input('cnpj');
    }
}
