<?php

declare(strict_types=1);

namespace App\Http\Requests\HealthReceiptBatches;

use App\Helpers\DateTimeHelper;
use App\Helpers\StringHelper;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:d/m/Y'],
            'end_date' => ['required', 'date_format:d/m/Y', 'after_or_equal:start_date'],
            'patient_id' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'A data final deve ser igual ou posterior à data inicial.',
        ];
    }

    public function toPayload(): array
    {
        $patientId = StringHelper::decrypt($this->input('patient_id'));

        return [
            'start_date' => DateTimeHelper::convertToDatabase($this->input('start_date'), 'Y-m-d'),
            'end_date' => DateTimeHelper::convertToDatabase($this->input('end_date'), 'Y-m-d'),
            'patient_id' => $patientId ? (int) $patientId : null,
        ];
    }
}
