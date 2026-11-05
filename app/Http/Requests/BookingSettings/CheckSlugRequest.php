<?php

declare(strict_types=1);

namespace App\Http\Requests\BookingSettings;

use Illuminate\Foundation\Http\FormRequest;

class CheckSlugRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'slug' => 'endereço da página',
        ];
    }
}
