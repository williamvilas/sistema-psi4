<?php

declare(strict_types=1);

namespace App\Http\Requests\BookingSettings;

use Illuminate\Foundation\Http\FormRequest;

class ToggleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'public_booking_enabled' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'public_booking_enabled' => 'página pública de agendamento',
        ];
    }
}
