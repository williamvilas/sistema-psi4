<?php

declare(strict_types=1);

namespace App\Http\Requests\ScaleLinks;

use App\Helpers\StringHelper;
use Illuminate\Foundation\Http\FormRequest;

class RevokeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'string'],
        ];
    }

    public function getId(): int
    {
        return (int) StringHelper::decrypt($this->input('id'));
    }
}
