<?php

declare(strict_types=1);

namespace App\Http\Requests\SessionTranscriptions;

use Illuminate\Foundation\Http\FormRequest;

class AcceptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes_path' => ['required', 'string', 'max:255', 'regex:/^medical-records\/[A-Za-z0-9._-]+$/'],
        ];
    }
}
