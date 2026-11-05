<?php

declare(strict_types=1);

namespace App\Http\Requests\Zipcode;

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
            'zipcode' => preg_replace('/\D/', '', (string) $this->route('zipcode')),
        ]);
    }

    public function rules(): array
    {
        return [
            'zipcode' => ['required', 'digits:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'zipcode.required' => __('zipcode.invalid'),
            'zipcode.digits' => __('zipcode.invalid'),
        ];
    }

    public function getZipcode(): string
    {
        return (string) $this->input('zipcode');
    }
}
