<?php

declare(strict_types=1);

namespace App\Http\Requests\Files;

use Illuminate\Foundation\Http\FormRequest;

class TemporaryUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'path' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (pathinfo($value, PATHINFO_EXTENSION) === '') {
                        $fail('O campo :attribute deve possuir uma extensão.');
                    }
                },
            ],

            'content_type' => ['required', 'string', 'in:text/html,application/octet-stream'],
        ];
    }
}
