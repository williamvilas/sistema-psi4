<?php

declare(strict_types=1);

namespace App\Http\Requests\MedicalRecords;

use App\Helpers\StringHelper;
use Illuminate\Foundation\Http\FormRequest;

class ExportRequest extends FormRequest
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

    public function getPatientId(): ?int
    {
        $id = StringHelper::decrypt($this->input('patient_id'));

        return $id ? (int) $id : null;
    }
}
