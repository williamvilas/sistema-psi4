<?php

declare(strict_types=1);

namespace App\Http\Requests\VideoRoomAuthorizations;

use Illuminate\Foundation\Http\FormRequest;

class ApproveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'proof' => ['required', 'string', 'max:64'],
        ];
    }
}
