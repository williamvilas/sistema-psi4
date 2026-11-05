<?php

declare(strict_types=1);

namespace App\Http\Requests\ScaleApplications;

use App\Helpers\StringHelper;
use Illuminate\Foundation\Http\FormRequest;

class ListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'string'],
        ];
    }

    public function getPatientId(): int
    {
        return (int) StringHelper::decrypt($this->input('patient_id'));
    }
}
