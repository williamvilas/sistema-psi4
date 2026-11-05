<?php

declare(strict_types=1);

namespace App\Http\Requests\SessionActivities;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePinRequest extends FormRequest
{
    private const array SEQUENCES = ['012345', '123456', '234567', '345678', '456789', '987654', '876543', '765432', '654321', '543210'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pin' => ['required', 'string', 'digits:6', 'confirmed', function (string $attribute, mixed $value, Closure $fail) {
                if (count(array_unique(str_split((string) $value))) === 1 || in_array($value, self::SEQUENCES, true)) {
                    $fail(__('session-activities.pin_weak'));
                }
            }],
        ];
    }
}
