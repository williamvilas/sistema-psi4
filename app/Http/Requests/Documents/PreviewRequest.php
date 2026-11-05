<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Enums\DocumentFrequencyEnum;
use App\Http\Requests\CustomRequest;
use Illuminate\Validation\Rule;

class PreviewRequest extends CustomRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'date' => ['nullable', 'date_format:d/m/Y'],
            'frequency' => ['nullable', Rule::enum(DocumentFrequencyEnum::class)],
        ];

        foreach (range(1, 6) as $n) {
            $rules['text_'.$n] = ['nullable', 'string', 'max:5000'];
        }

        return $rules;
    }
}
