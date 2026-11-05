<?php

declare(strict_types=1);

namespace App\Http\Requests\GoogleCalendar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConnectRequest extends FormRequest
{
    public const ORIGIN_AGENDA = 'agenda';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'string', Rule::in([self::ORIGIN_AGENDA])],
        ];
    }

    public function getOrigin(): ?string
    {
        return $this->filled('from') ? (string) $this->input('from') : null;
    }
}
