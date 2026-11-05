<?php

declare(strict_types=1);

namespace App\Http\Requests\ScaleLinks;

use App\Helpers\StringHelper;
use Illuminate\Foundation\Http\FormRequest;

class PaginateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function getPatientId(): int
    {
        return (int) StringHelper::decrypt($this->input('patient_id'));
    }
}
