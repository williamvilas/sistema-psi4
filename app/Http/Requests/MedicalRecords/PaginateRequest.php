<?php

declare(strict_types=1);

namespace App\Http\Requests\MedicalRecords;

use App\Helpers\DateTimeHelper;
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
        return [];
    }

    public function getParams(): array
    {
        $params = $this->only(['q', 'patient_id', 'date']);
        $params = array_filter($params, fn ($v) => is_string($v) && $v !== '');

        if (! empty($params['patient_id'])) {
            $patientId = StringHelper::decrypt($params['patient_id']);

            if ($patientId) {
                $params['patient_id'] = (int) $patientId;
            } else {
                unset($params['patient_id']);
            }
        }

        if (! empty($params['date'])) {
            $dates = explode(' - ', $params['date']);

            if (count($dates) === 2) {
                $params['start_date'] = DateTimeHelper::convertToDatabase($dates[0], 'Y-m-d');
                $params['end_date'] = DateTimeHelper::convertToDatabase($dates[1], 'Y-m-d');
            }
        }

        return $params;
    }
}
