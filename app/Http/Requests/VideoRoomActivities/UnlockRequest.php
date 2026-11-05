<?php

declare(strict_types=1);

namespace App\Http\Requests\VideoRoomActivities;

use Illuminate\Foundation\Http\FormRequest;

class UnlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pin' => ['required', 'string', 'digits:6'],
        ];
    }
}
